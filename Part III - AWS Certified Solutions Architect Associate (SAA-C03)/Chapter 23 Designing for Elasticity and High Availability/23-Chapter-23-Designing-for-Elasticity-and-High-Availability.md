# Chapter 23: Designing for Elasticity and High Availability

---

Chapter 13 covered load balancers, CloudWatch, and Auto Scaling groups. This chapter covers designing with them for availability targets. Design Resilient Architectures is 26% of SAA-C03, and most of it sits here and in Chapter 29.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 23.1 Multi-AZ and Multi-Region Patterns

**What each protects against**

| Failure | Multi-AZ | Multi-Region |
| --- | --- | --- |
| Single instance failure | Yes | Yes |
| Availability Zone failure | Yes | Yes |
| Regional service impairment | No | Yes |
| Regional natural disaster | No | Yes |
| Bad deployment or logical corruption | No, it replicates | Only if the second Region lags deliberately |
| Data residency requirement in a second country | No | Yes |

**Cost and complexity**

Multi-AZ is cheap and simple. Inter-AZ data transfer is charged, and a standby database roughly doubles the instance cost, but the architecture is otherwise the same.

Multi-Region is expensive and hard. Data replication is asynchronous, so there is data loss on failover unless the application is designed around it. Deployments must reach both Regions. Failover must be tested, and testing it is disruptive. Costs roughly double, plus cross-Region transfer.

**The design rule.** Start multi-AZ. It handles the failures that actually occur, and it is what the exam expects by default. Move to multi-Region only when a stated requirement demands it: a recovery time or recovery point objective that a single Region cannot meet, a regulatory requirement for geographic separation, or global users needing local write latency.

**Static stability** is the pattern behind well-designed multi-AZ systems. Provision enough capacity in each zone that losing one requires no scaling action to survive. An Auto Scaling group across three zones running at 66% utilization absorbs a zone loss immediately; one running at 100% must launch instances during the incident, which is exactly when control plane operations are least reliable.

---

## 23.2 Scaling Policies in Depth

| Policy | How it works | Choose when |
| --- | --- | --- |
| Target tracking | Keeps a metric at a target value, managing its own alarms | Default choice; the metric correlates with load |
| Step scaling | Adjusts by different amounts depending on how far the threshold is breached | Response must be proportional to severity |
| Simple scaling | One adjustment per alarm, with a cooldown before the next | Legacy; step scaling supersedes it |
| Scheduled | Changes capacity at fixed times | Load is predictable by clock or calendar |
| Predictive | Forecasts from history and provisions ahead of demand | Recurring daily or weekly patterns, and warm-up time is significant |

**Choosing the metric.** Average CPU is the default and is often wrong. Better metrics depending on the workload:

- `ALBRequestCountPerTarget` for web tiers, since it tracks work arriving rather than a symptom of it.
- SQS `ApproximateNumberOfMessagesVisible` divided by instance count for queue workers.
- Custom application metrics such as active sessions or queue depth.

The test is whether the metric rises before the user notices. CPU on a memory-bound or I/O-bound application does not.

**Warm-up and cooldown**

- **Instance warmup** tells the group how long a new instance takes to contribute, so its early metrics do not distort the aggregate and trigger further scaling.
- **Cooldown** prevents another scaling action while the previous one settles.
- Both being too short causes thrashing: the group scales out, the metric has not yet responded, so it scales out again, then scales in hard.

**Scale out fast, scale in slow.** Scaling out late costs availability; scaling in early costs availability too, and scaling in late costs only money. Default behavior already reflects this, and designs should not fight it.

**Lifecycle hooks** pause an instance in `Pending:Wait` or `Terminating:Wait` so work can complete: registering with a configuration system on launch, or draining connections and flushing logs on termination.

**Termination policy** determines which instance goes when scaling in. The default balances across Availability Zones first, which preserves the zone symmetry the design depends on.

---

## 23.3 Health Checks and Automatic Recovery

Three independent health check systems exist, and they do different things.

| Check | Evaluates | Acts by |
| --- | --- | --- |
| EC2 status checks | Whether the instance and its host are running | Auto Scaling replaces the instance |
| ELB health checks | Whether the application responds correctly on the target | Load balancer stops routing; Auto Scaling replaces if configured |
| Route 53 health checks | Whether an endpoint is reachable from the internet | DNS stops returning the record |

**Enable ELB health checks on the Auto Scaling group.** By default a group uses EC2 status checks only, which pass while the application is broken. This is the most common availability gap in otherwise reasonable designs.

**Design the health check endpoint properly.**

- A path returning 200 unconditionally is worse than useless, because it hides failure.
- A path that checks every dependency is also wrong: a database blip then fails every instance simultaneously and the whole fleet is replaced.
- The useful middle is a shallow check confirming the process is serving requests, with a separate deep check used by monitoring rather than by the load balancer.

**Tuning.** Interval, timeout, and healthy and unhealthy thresholds determine detection time. Unhealthy threshold 2 with a 10-second interval detects in about 20 seconds. Longer values are slower to detect; shorter values risk removing healthy targets during a transient blip.

**Grace period.** The health check grace period must exceed the time from launch to the application serving traffic. Set it too short and the group terminates instances that were still starting, then launches replacements that do the same, producing a loop that looks like a crash.

**Connection draining**, called deregistration delay on ALB and NLB, lets in-flight requests finish before a target is removed. The default is 300 seconds, which is longer than most applications need and slows deployments unnecessarily.

**EC2 Auto Recovery** recovers an instance on new hardware after an underlying host failure, preserving the instance ID, private IP, and EBS volumes. It handles host failure, not application failure.

---

## 23.4 Stateless Application Design

Statelessness is what makes horizontal scaling possible. If a request must return to the instance that handled the previous one, instances cannot be added or removed freely.

**Where state goes**

| State | Destination |
| --- | --- |
| Session data | ElastiCache for Redis, or DynamoDB with TTL |
| Uploaded files | Amazon S3 |
| Shared configuration | Systems Manager Parameter Store or AppConfig |
| Secrets | AWS Secrets Manager |
| Persistent application data | The database |
| Shared file access | Amazon EFS |

**Sticky sessions** bind a client to one target. They are a workaround, not a design: they unbalance load, and losing the target still loses the session. Use them only where an application cannot be changed, and treat them as debt.

**What statelessness buys**

- Instances become disposable, so replacement is a routine operation rather than an incident.
- Scaling in does not lose user sessions.
- Deployments can replace instances without draining state.
- Spot Instances become viable, since an interruption costs nothing.

---

## 23.5 Route 53 Routing for Availability

The full policy list is in section 9.6. What matters here is which policy answers which availability requirement.

| Requirement | Policy |
| --- | --- |
| Automatic failover to a standby site | Failover, with a health check on the primary |
| Send users to the fastest Region for them | Latency-based |
| Comply with content or regulatory rules by country | Geolocation |
| Shift load between Regions with a deliberate bias | Geoproximity with a bias value |
| Gradual migration or canary release | Weighted, adjusting weights over time |
| Simple client-side spreading with health checking | Multivalue answer |

**Health checks are what make routing highly available.** A failover policy without a health check on the primary never fails over. Route 53 health checks can monitor an endpoint, another health check as a calculated check, or a CloudWatch alarm, which allows failover on a business metric rather than only on reachability.

**DNS failover is slower than it looks.** Detection takes the health check interval multiplied by the failure threshold. Then the record's TTL must expire on resolvers and clients. A 60-second TTL plus a 90-second detection is roughly two and a half minutes before traffic moves, and some clients cache longer than they should. Where seconds matter, DNS is the wrong mechanism and Global Accelerator is the answer.

**Alias records** point at AWS resources, work at the zone apex where CNAMEs cannot, are not charged per query, and automatically track the resource's address changes.

---

## 23.6 Global Traffic Distribution

| Service | Operates at | Use |
| --- | --- | --- |
| Amazon CloudFront | HTTP and HTTPS, caching at the edge | Static and cacheable dynamic content, and reducing origin load |
| AWS Global Accelerator | TCP and UDP, no caching | Non-HTTP protocols, fast regional failover, static entry-point IPs |
| Route 53 latency routing | DNS | Directing users to a Region, subject to DNS caching |

**Global Accelerator** provides two static anycast IP addresses advertised from AWS edge locations. Traffic enters the AWS backbone at the nearest edge and is routed to the healthiest endpoint. Because the IPs never change, failover is not subject to DNS caching and typically completes in around 30 seconds.

**Choosing between them**

- Cacheable web content: **CloudFront**.
- Gaming, VoIP, IoT, or any non-HTTP protocol: **Global Accelerator**.
- Fast regional failover with fixed IP addresses, for example because a client firewall allows specific addresses: **Global Accelerator**.
- Simple regional steering where a couple of minutes of failover is acceptable: **Route 53**.

They combine. A common design uses CloudFront for content, Global Accelerator for the API, and Route 53 in front of both.

---

## 23.7 Observability for Architects

**Composite alarms** combine several alarms with AND and OR logic, so a page fires when the condition genuinely indicates a problem rather than when any single metric wobbles. This is the standard fix for alert fatigue.

**CloudWatch Logs Insights** queries log groups with a purpose-built query language, which is how you answer "which requests failed and what did they have in common" without building a pipeline.

**CloudWatch Synthetics** runs canaries that exercise an endpoint on a schedule from outside the application, so an outage is detected even when every internal metric looks healthy.

**AWS X-Ray** traces a request across services and produces a service map showing where latency accumulates. It is the tool for "the API is slow" when every individual component reports normal.

**CloudWatch Application Signals** provides application performance monitoring with service-level objectives, tracking whether a service meets a defined availability or latency target over a period.

**Anomaly detection** builds a band from historical behavior and alarms on deviation, which suits metrics with a daily or weekly shape where a static threshold is either too noisy or too slow.

**What to alarm on.** Alarm on symptoms users experience, such as error rate, latency percentiles, and healthy host count. Monitor causes, such as CPU and memory, without paging on them. An alarm nobody acts on should be deleted or downgraded.

---

## 23.8 Service Quotas and Throttling

Quotas are a real constraint on availability and are routinely overlooked until an incident.

- Most quotas are **per Region per account**, so a multi-Region design does not inherit headroom from elsewhere.
- Some are **adjustable** through Service Quotas or a support case; others are hard limits that require a design change.
- Quota increases take time to approve, so requesting them during an incident is too late.

**Quotas that commonly bite**

| Quota | Consequence |
| --- | --- |
| Running On-Demand vCPUs per instance family | Auto Scaling cannot launch during a traffic surge |
| Elastic IP addresses per Region, default 5 | NAT gateway or load balancer creation fails |
| Lambda concurrent executions, default 1,000 | Functions throttle under load |
| VPCs, subnets, and route table entries per VPC | Landing zone expansion stalls |
| EBS storage per Region per volume type | Volume creation fails during scaling |

**Design responses**

- Set CloudWatch alarms on quota utilization through the Service Quotas integration.
- Request increases as part of capacity planning, ahead of a launch or seasonal peak.
- Use **reserved concurrency** on Lambda so one function cannot consume the account's whole pool and throttle everything else.
- Implement **exponential backoff with jitter** on every AWS API call. Retrying immediately during throttling makes throttling worse; the AWS SDKs do this by default and custom code often does not.

---

## 23.9 End-of-Chapter Questions

**Q1.** An Auto Scaling group replaces instances that pass EC2 status checks but return HTTP 500 errors to users. What change fixes this?

- A. Reduce the health check grace period
- B. Enable ELB health checks on the Auto Scaling group
- C. Increase the desired capacity
- D. Switch to step scaling

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* EC2 status checks confirm the instance is running, not that the application is healthy; the group must consider the load balancer's view to replace failing instances.

**Q2.** A multiplayer game uses UDP and requires failover between Regions in seconds, with a fixed set of IP addresses that players' firewalls allow. Which service fits?

- A. Amazon CloudFront
- B. Route 53 latency-based routing
- C. AWS Global Accelerator
- D. Application Load Balancer with cross-zone load balancing

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Global Accelerator supports UDP, provides static anycast IPs, and fails over without waiting for DNS caches to expire; CloudFront handles HTTP only.

**Q3.** An application scales out repeatedly and then scales in sharply, oscillating under steady traffic. What is the most likely cause?

- A. The desired capacity is set too low
- B. Instance warmup and cooldown periods are too short, so new instances are counted before they contribute
- C. The Auto Scaling group spans too many Availability Zones
- D. Detailed monitoring is disabled

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Without adequate warmup, the metrics of still-starting instances distort the group average and trigger further scaling, producing oscillation.

**Q4.** An architect wants a three-Availability-Zone deployment to survive the loss of one zone without launching any new instances during the incident. What is this design principle called, and what does it require?

- A. Elasticity, requiring aggressive scaling policies
- B. Static stability, requiring enough provisioned capacity that the remaining zones absorb the load
- C. Fault tolerance, requiring a Multi-AZ database
- D. Loose coupling, requiring a message queue

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Static stability avoids depending on control plane operations during a failure, which is when they are least reliable.

**Q5.** A Route 53 failover routing policy has been configured but traffic never moves to the secondary when the primary fails. What is missing?

- A. A health check associated with the primary record
- B. An alias record for the secondary
- C. A lower TTL on the secondary record
- D. Multivalue answer routing

**Answer: A.** *Target exam: AWS Certified Solutions Architect - Associate.* Failover routing acts on health check status; without a health check on the primary, Route 53 has no signal that it has failed.

**Q6.** During a traffic surge an Auto Scaling group fails to launch new instances, reporting a vCPU limit error. What should have been done in advance?

- A. Enabled predictive scaling
- B. Monitored the account's On-Demand vCPU quota and requested an increase ahead of the peak
- C. Reduced the instance size
- D. Enabled cross-zone load balancing

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Quotas are per Region per account and increases take time to approve, so capacity planning must include them rather than discovering the limit during an incident.
