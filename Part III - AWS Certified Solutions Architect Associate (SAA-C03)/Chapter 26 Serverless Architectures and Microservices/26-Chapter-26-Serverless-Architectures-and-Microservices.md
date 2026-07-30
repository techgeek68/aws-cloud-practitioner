# Chapter 26: Serverless Architectures and Microservices

---

Section 19.6 covered Lambda's constraints: concurrency, cold starts, VPC attachment, the connection problem and RDS Proxy, and memory as the CPU dial. This chapter covers assembling serverless components into an architecture, and the API layer in front of them.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 26.1 A Reference Serverless Architecture

The canonical web application, with nothing to patch and nothing running when idle:

```
        Users
          |
    Amazon Route 53
          |
    Amazon CloudFront  ---- static assets ----> Amazon S3
          |
   Amazon API Gateway  <---- authorization ---- Amazon Cognito
          |
      AWS Lambda
          |
   +------+------+-------------+
   |             |             |
Amazon      Amazon S3    Amazon SQS
DynamoDB                      |
                          AWS Lambda
                        (async workers)
```

**What each piece contributes**

- **CloudFront and S3** serve the frontend, so static content never reaches compute.
- **API Gateway** terminates TLS, authorizes, throttles, and routes.
- **Cognito** authenticates application users, as covered in section 17.5.2.
- **Lambda** runs the request handlers.
- **DynamoDB** holds application data, with no connection pool to exhaust.
- **SQS with a second set of Lambda functions** takes slow work out of the request path, so the API responds immediately and the work completes asynchronously.

**Why this shape recurs.** Each layer scales independently, nothing is billed while idle, and there is no operating system anywhere. Its costs are cold start latency, per-service quotas, and a system that is harder to trace than a monolith.

**Where it stops fitting.** Sustained high-volume traffic, where always-on containers cost less; long-running processing, where Lambda's 15-minute ceiling applies; and workloads with strict single-digit millisecond latency requirements where cold starts are unacceptable and provisioned concurrency is too expensive at the required scale.

---

## 26.2 Amazon API Gateway

**The three API types**

| | REST API | HTTP API | WebSocket API |
| --- | --- | --- | --- |
| Cost | Highest | Roughly 70% lower | Per message and connection minute |
| Latency | Higher | Lower | n/a |
| Features | Request and response transformation, API keys and usage plans, WAF integration, caching, private endpoints, request validation | Core routing, JWT and Lambda authorizers, CORS | Bidirectional messaging |
| Choose when | Those features are needed | They are not | Server needs to push to clients |

HTTP APIs are the default for new work. Reach for REST APIs when a listed feature is genuinely required, most often caching, usage plans, WAF, or request transformation.

**Endpoint types**

- **Edge-optimized** routes through CloudFront edge locations, for globally distributed clients.
- **Regional** serves from the Region, appropriate when clients are in the same Region or when you want your own CloudFront distribution in front.
- **Private** is reachable only from within a VPC through an interface endpoint, for internal APIs.

**Authorization options**

| Mechanism | Use |
| --- | --- |
| IAM authorization | Callers are AWS principals, or service-to-service calls |
| Cognito user pool authorizer | Application users authenticated by Cognito |
| Lambda authorizer | Custom logic, third-party identity providers, or token formats Cognito does not handle |
| JWT authorizer, HTTP APIs | Any OIDC-compliant provider |
| API keys with usage plans | Identifying and metering callers; not an authentication mechanism on its own |

API keys are frequently misused. They identify a caller for throttling and metering. They are not a security control, because they travel in a header and can be copied.

**Throttling** applies at account, stage, method, and usage plan level, protecting the backend from a caller sending more than it should. Requests over the limit receive HTTP 429. Defining throttles is part of the design, not an afterthought, because Lambda concurrency is a shared account resource and one runaway caller can starve everything else.

**Caching**, available on REST APIs, caches responses at the stage for a configured TTL, cutting both latency and backend invocations. It is charged by cache size per hour.

**Integration types**

- **Lambda proxy integration** passes the whole request to the function and expects a correctly shaped response. Simplest, and the usual choice.
- **Lambda non-proxy** uses mapping templates to transform requests and responses, keeping transformation logic out of the function.
- **HTTP integration** to any HTTP endpoint.
- **AWS service integration** calls an AWS service directly, with no Lambda function at all. Putting a message on an SQS queue or writing to DynamoDB straight from API Gateway removes a function, its cost, and its cold start.

That last point is worth internalizing: a Lambda function that only forwards a request to another AWS service is usually unnecessary.

---

## 26.3 Lambda in Production

**Versions and aliases**

- Publishing a **version** creates an immutable snapshot of code and configuration, numbered sequentially. `$LATEST` is mutable and should not be what production points at.
- An **alias** is a named pointer to a version, such as `prod` or `staging`. Callers reference the alias, so promoting a release is a matter of repointing it.
- **Weighted aliases** split traffic between two versions by percentage, which is how canary deployment works on Lambda.

**Layers** package shared dependencies separately from function code. Up to five layers per function, counting toward the 250 MB unzipped limit. They reduce deployment package size and let several functions share a common library, at the cost of another artifact to version.

**Container images** are the alternative packaging format, up to 10 GB, useful for large dependencies or when the team already builds containers.

**Event source mappings** are what let Lambda consume from sources that do not push: SQS, Kinesis, DynamoDB Streams, Amazon MSK, and Amazon MQ. Lambda polls on your behalf.

- **Batch size** controls how many records arrive per invocation. Larger batches are more efficient and increase the blast radius of a failure.
- **Batch window** waits to accumulate records before invoking, trading latency for efficiency.
- **On SQS**, a failed batch returns all its messages to the queue unless partial batch response is enabled, which reports only the failed message IDs.
- **On Kinesis and DynamoDB Streams**, a failing record blocks its shard until it succeeds or expires. Configure a maximum retry count, a maximum record age, and an on-failure destination, or one poison record halts the shard indefinitely.

**Destinations** route the result of an asynchronous invocation to SQS, SNS, Lambda, or EventBridge, separately for success and failure. This is cleaner than a dead-letter queue because it captures the invocation record and the response.

**Idempotency.** Lambda guarantees at-least-once delivery for most asynchronous and stream sources, so a function must tolerate being invoked twice with the same event. Use a deduplication key in DynamoDB, or make the operation naturally idempotent.

---

## 26.4 Microservice Boundaries

**Sizing a service.** The useful boundary is a business capability owned by one team, not a technical layer and not a fixed line count. "Orders", "payments", and "inventory" are services. "The database layer" is not.

**The data ownership rule.** Each service owns its data and no other service reads that database directly. Sharing a database recreates the coupling microservices were meant to remove: a schema change breaks other services, and nobody can deploy independently. Other services get data through the owning service's API or through events it publishes.

**Consequences to design for**

- **No cross-service joins.** Data that used to be joined in one query now requires either an API call or a locally maintained copy built from events.
- **No distributed transactions.** Consistency across services becomes eventual. The **saga pattern** implements a multi-step operation as a sequence of local transactions with compensating actions on failure, and Step Functions is the natural way to coordinate one.
- **Failure is partial.** One service being down should degrade the system, not stop it. Timeouts, retries with backoff, and circuit breakers belong in every caller.
- **Observability is not optional.** A request crossing six services cannot be debugged from six separate log groups. Distributed tracing with X-Ray is what makes this tractable.

**Do not start here.** Microservices trade operational simplicity for team independence. That trade only pays when there are enough teams for coordination to be the bottleneck. A small team building a new product should start with a well-structured monolith and split it when the seams are known, because the boundaries are almost never obvious at the start.

---

## 26.5 Serverless Data Access

**DynamoDB is the natural fit.** It is an HTTP API with no persistent connections, so thousands of concurrent Lambda invocations do not exhaust anything. Combined with on-demand capacity, it scales with the function.

**Relational databases need care**, for the reasons in section 19.6. Use RDS Proxy, keep functions outside the VPC unless they must reach private resources, and consider Aurora Serverless v2 where load is intermittent.

**The Aurora Data API** provides an HTTP endpoint for Aurora Serverless, removing connection management entirely and allowing a function outside the VPC to query the database. It is slower per query than a direct connection and suits low to moderate query rates.

**Initialization outside the handler.** Code in the initialization phase runs once per execution environment, not once per invocation. Creating clients, loading configuration, and establishing connections there means they are reused across invocations on a warm environment. This is the single most effective serverless performance practice and costs nothing.

**Secrets and parameters.** Fetch from Secrets Manager or Parameter Store during initialization and cache in memory. Fetching on every invocation adds latency, cost, and throttling risk. The Parameters and Secrets Lambda Extension provides local caching automatically.

**Sensible defaults for connections**

- DynamoDB: direct, with DAX if microsecond reads are needed.
- Aurora or RDS at high concurrency: RDS Proxy.
- Aurora Serverless at moderate rates, function outside the VPC: Data API.
- ElastiCache: requires VPC attachment, so weigh that cost.

---

## 26.6 Serverless Trade-Offs

**Cost.** Excellent for intermittent, spiky, and low-volume workloads, where idle costs nothing. Worse than containers for sustained high volume, because per-invocation pricing accumulates while a container's cost is flat. The crossover depends on invocation rate and duration; model it rather than assuming either direction.

**Latency.** Cold starts add tens to hundreds of milliseconds depending on runtime and package size, and more for VPC-attached functions on first use. Mitigate with smaller packages, lighter runtimes, and initialization outside the handler; eliminate for a fixed count with provisioned concurrency.

**Vendor coupling.** A serverless architecture is deeply tied to its provider's services. That is a real consideration and usually a smaller one than it appears, because the alternative is building and operating equivalents.

**Testing.** Local testing is harder, because the environment is a composition of managed services. AWS SAM and LocalStack help; integration testing against real deployed resources in an isolated account is generally more honest.

**Quotas as design constraints.** Account-level Lambda concurrency, API Gateway throttle limits, and per-service limits all bind. Reserved concurrency stops one function consuming the pool, as covered in section 23.8.

**When to choose serverless**

| Signal | Points to |
| --- | --- |
| Unpredictable or spiky traffic | Serverless |
| Long idle periods | Serverless |
| Event-driven processing | Serverless |
| Small team, low operational capacity | Serverless |
| Sustained high-volume traffic | Containers |
| Processing exceeding 15 minutes | Containers, AWS Batch, or Step Functions coordinating shorter tasks |
| Strict low-latency requirement at scale | Containers |
| Existing containerized application | Containers |

---

## 26.7 End-of-Chapter Questions

**Q1.** A team needs a low-cost HTTP API in front of Lambda, with JWT authorization from an existing OIDC provider and no requirement for caching, usage plans, or request transformation. Which API Gateway type fits?

- A. REST API, edge-optimized
- B. REST API, private
- C. HTTP API
- D. WebSocket API

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* HTTP APIs cost substantially less and support JWT authorizers natively; the REST API's additional features are not required here.

**Q2.** An API endpoint receives a request and its Lambda function does nothing but place the payload on an SQS queue. How can the architecture be simplified?

- A. Increase the function's memory allocation
- B. Use an API Gateway AWS service integration to write directly to SQS, removing the function
- C. Replace SQS with SNS
- D. Enable provisioned concurrency on the function

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* API Gateway can call AWS services directly, removing a function that adds cost, latency, and a cold start without doing any work.

**Q3.** A Lambda function consuming a Kinesis stream encounters a record it cannot process. Processing for that shard stops entirely. What should be configured?

- A. Increase the batch size
- B. Set a maximum retry count and record age, with an on-failure destination
- C. Switch the stream to a standard SQS queue
- D. Enable provisioned concurrency

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Without retry and age limits and a failure destination, a poison record blocks its shard until it expires, halting all processing for that shard.

**Q4.** Production traffic must move gradually from version 4 to version 5 of a Lambda function, with the ability to roll back immediately. What supports this?

- A. Publishing version 5 and updating `$LATEST`
- B. A weighted alias splitting traffic between the two versions
- C. Two separate functions behind an Application Load Balancer
- D. Layers containing each version

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Weighted aliases shift a percentage of invocations between versions and revert by changing the weight, which is how canary deployment works on Lambda.

**Q5.** Two microservices both read and write the same RDS database directly. What problem does this create?

- A. Increased database storage cost
- B. The services are coupled through the schema, so neither can change it or deploy independently
- C. The database cannot be encrypted
- D. Connection pooling becomes impossible

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* A shared database recreates exactly the coupling microservices exist to remove; each service should own its data and expose it through an API or events.

**Q6.** A Lambda function fetches a database credential from AWS Secrets Manager on every invocation, adding latency and cost. What is the correct fix?

- A. Store the credential in an environment variable in plaintext
- B. Fetch the secret during the initialization phase outside the handler so it is reused across invocations on a warm environment
- C. Increase the function timeout
- D. Move the function into a VPC

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Initialization code runs once per execution environment rather than once per invocation, so fetching and caching there removes the repeated call without weakening security.
