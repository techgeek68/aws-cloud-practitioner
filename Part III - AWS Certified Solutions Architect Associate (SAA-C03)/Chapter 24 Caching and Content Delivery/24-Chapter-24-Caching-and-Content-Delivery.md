# Chapter 24: Caching and Content Delivery

---

Section 9.7 covered what CloudFront is. Section 20.5 covered ElastiCache engines, the lazy loading and write-through strategies, and DAX. This chapter covers where each cache belongs in an architecture and how to configure the edge properly.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 24.1 Where Caching Belongs

Caching is applied in layers, and each layer removes work from the one behind it.

| Layer | Mechanism | Removes |
| --- | --- | --- |
| Client | Browser cache, `Cache-Control` headers | The request entirely |
| Edge | Amazon CloudFront | The trip to the Region |
| Application | ElastiCache, in-process cache | Recomputation and repeated database reads |
| Database | ElastiCache, DynamoDB Accelerator | Repeated identical queries |
| Storage | S3 with CloudFront in front | Repeated object reads and egress charges |

**Cache closest to the user first.** A request served from a browser cache costs nothing and takes no time. One served from an edge location costs a fraction of one served from the origin. Adding a database cache while leaving edge caching unconfigured is optimizing the wrong layer.

**What is worth caching**

- Read frequently, written rarely.
- Expensive to produce relative to its size.
- Tolerant of being slightly stale, or has a bounded staleness the business accepts.

**What is not**

- Data written as often as it is read, where the cache is invalidated before it is used.
- Data unique to every request, which never produces a hit.
- Anything where stale data causes a correctness problem the application cannot detect.

---

## 24.2 CloudFront Design

**Origins and origin groups**

- An origin can be an S3 bucket, an Application Load Balancer, an API Gateway endpoint, a Lambda function URL, or any HTTP server, including one outside AWS.
- **Origin groups** pair a primary and a secondary origin with failover criteria, so CloudFront retries against the secondary on specified status codes. This is an availability mechanism as well as a caching one.
- One distribution can have several origins, selected by **cache behaviors** on path patterns, so `/api/*` reaches a load balancer while `/static/*` reaches a bucket.

**Cache behaviors** are evaluated in order, with the default behavior last. Each controls the origin, allowed methods, whether the response is cached, TTL handling, and which request elements are forwarded and used in the cache key.

**Cache key design** is the part most often got wrong.

- The cache key determines what counts as the same request. Everything included in it fragments the cache.
- Forwarding all headers, cookies, or query strings drives the hit ratio toward zero, because each variation becomes a separate cached object.
- Include only what genuinely changes the response. If the response does not vary by `User-Agent`, do not include it.
- **Cache policies** define the cache key and TTLs. **Origin request policies** define what is forwarded to the origin without affecting the cache key, which is how you send a header the origin needs for logging without fragmenting the cache.

**TTL control**

- The origin's `Cache-Control` and `Expires` headers set the TTL, with minimum, maximum, and default TTLs on the behavior as bounds.
- Set TTLs deliberately at the origin. Relying on defaults produces either stale content or a poor hit ratio.
- **Versioned object names**, such as `app.a3f9c2.js`, allow effectively infinite TTLs and make invalidation unnecessary. This is preferable to invalidating on every deploy.

**Invalidation** removes objects before their TTL expires. The first 1,000 paths per month are free and further paths are charged. Invalidating `/*` on every deployment is a sign the naming strategy needs fixing.

**Compression.** CloudFront can compress objects with Gzip or Brotli when the viewer supports it, which reduces transfer cost and improves load time. It applies only to compressible content types and only when the origin does not already compress.

**Price classes** restrict the distribution to a subset of edge locations, trading some latency in distant geographies for lower cost. This is a legitimate cost lever when the user base is regional.

---

## 24.3 Origin Protection

A distribution in front of a publicly readable origin protects nothing, because clients can bypass it.

**For S3 origins: origin access control (OAC).**

1. Create the distribution with the bucket as origin and enable OAC.
2. CloudFront generates a bucket policy granting `s3:GetObject` to the CloudFront service principal, restricted to that distribution.
3. Apply it to the bucket.
4. Leave Block Public Access enabled.

OAC supersedes the older **origin access identity (OAI)**, and supports SSE-KMS encrypted objects and all HTTP methods, which OAI does not. Existing OAI configurations continue to work; new ones should use OAC.

**For custom origins**, such as a load balancer:

- Add a **custom header** with a secret value at the distribution, and configure the load balancer or WAF to reject requests without it.
- Restrict the load balancer's security group to the **CloudFront origin-facing prefix list**, so only CloudFront can reach it.
- Both together is the stronger design, since the prefix list allows all CloudFront distributions, not only yours.

**Restricting content to authorized viewers**

- **Signed URLs** grant access to one file for a limited period.
- **Signed cookies** grant access to multiple files without changing their URLs, which suits streaming and paywalled sections.
- **Geographic restriction** allows or blocks by country at the edge.
- **AWS WAF** on the distribution filters by rule before requests reach the origin.
- **Field-level encryption** encrypts specific form fields at the edge so only the intended application can decrypt them.

---

## 24.4 Edge Compute

| | CloudFront Functions | Lambda@Edge |
| --- | --- | --- |
| Runtime | JavaScript, purpose-built | Node.js and Python |
| Triggers | Viewer request and viewer response | Viewer request and response, origin request and response |
| Execution time | Under 1 millisecond | Up to 5 seconds on viewer triggers, 30 on origin triggers |
| Network access | None | Yes |
| Access to request body | No | Yes on origin request |
| Scale and cost | Millions per second, very low cost | Thousands per second, higher cost |

**Choosing between them.** CloudFront Functions for lightweight manipulation on every request: header rewriting, URL normalization, cache key normalization, simple redirects, and token validation. Lambda@Edge when the logic needs a network call, the request body, or an origin trigger, such as fetching from a second origin, generating a response from a database, or resizing an image on first request.

**Origin triggers run only on cache misses**, which makes them far cheaper than viewer triggers for expensive logic. Placing work on an origin trigger rather than a viewer trigger is often the single best optimization available.

---

## 24.5 Application and Database Caching

The ElastiCache engine comparison, the lazy loading and write-through strategies, and DAX are covered in section 20.5. What matters architecturally is placement and failure behavior.

**Placement**

- ElastiCache sits in private subnets, reachable from the application tier only, with a security group referencing the application's security group.
- It is not a public service and has no internet-facing endpoint.
- Multi-AZ with automatic failover on Redis provides availability; a single-node cache is a single point of failure that takes the database load with it when it goes.

**Designing for cache failure**

This is the part designs usually omit. When the cache disappears, every request reaches the database at once.

- Size the database to survive a cold cache, or accept degraded service during the warm-up.
- Add jitter to TTLs so entries do not expire simultaneously, which is what causes a thundering herd.
- Use a request coalescing or locking pattern so that on a miss, one request populates the cache and the rest wait, rather than all of them querying the database.
- Fail open rather than closed: a cache error should fall through to the database, not return an error.

**Cluster mode.** Redis cluster mode shards data across node groups, which scales writes and memory beyond one node. Without it, the primary is a write and memory ceiling. Enabling it later requires migration, so decide at design time.

**Session storage.** ElastiCache for Redis and DynamoDB with TTL both work. Redis is faster and better suited to frequent updates; DynamoDB is serverless with no capacity to manage and no failover to design. For a low-traffic application, DynamoDB is often the simpler answer.

---

## 24.6 Caching Trade-Offs

**Staleness.** Every cache trades freshness for speed. The design question is not whether stale data is acceptable but how stale, for how long, and what happens when someone notices. Write that number down; it becomes the TTL.

**Invalidation complexity.** Invalidation is the hard part of caching. Designs that need precise, immediate invalidation across layers are usually a sign the data should not be cached, or that versioned naming should be used so nothing needs invalidating.

**Cost.** Caching is nearly always cheaper than not caching, because origin compute, database capacity, and data transfer all fall. The exceptions are caching data that is never read twice, where the cache cost is pure overhead, and provisioned cache capacity sized for a peak that never arrives.

**Debugging.** A cached system is harder to reason about, because behavior depends on state you cannot see. Include the cache status in responses during development, and make it possible to bypass the cache for a specific request when investigating.

**The order to apply caching**

1. Set correct `Cache-Control` headers at the origin. This is free and often the largest single improvement.
2. Put CloudFront in front of anything served over HTTP.
3. Cache expensive database reads in ElastiCache, or add DAX for DynamoDB.
4. Only then consider in-process caches, which are the hardest to invalidate and reason about.

---

## 24.7 End-of-Chapter Questions

**Q1.** A CloudFront distribution has a very low cache hit ratio. Investigation shows the cache behavior forwards all headers, cookies, and query strings to the origin. What should be changed?

- A. Increase the maximum TTL
- B. Restrict the cache key to only the headers, cookies, and query strings that change the response
- C. Enable compression
- D. Add a second origin

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Everything included in the cache key fragments the cache, so forwarding all request elements makes nearly every request unique.

**Q2.** Content must be served through CloudFront while the S3 bucket remains private with Block Public Access enabled. What should be configured?

- A. A bucket policy granting public read
- B. Origin access control, with the generated bucket policy applied to the bucket
- C. S3 static website hosting as the origin
- D. Signed cookies on the distribution

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* OAC lets CloudFront read the bucket through the service principal while the bucket stays private; the S3 website endpoint requires public access.

**Q3.** An application needs to rewrite a request header on every incoming request, with the lowest possible latency and cost. Which option fits?

- A. Lambda@Edge on the viewer request trigger
- B. CloudFront Functions on the viewer request trigger
- C. Lambda@Edge on the origin request trigger
- D. AWS WAF with a custom rule

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* CloudFront Functions run in under a millisecond at very low cost and are designed for header and URL manipulation; Lambda@Edge is heavier than this task requires.

**Q4.** An ElastiCache node fails and the database is immediately overwhelmed by requests that previously hit the cache. Which design change reduces the impact of a future cache failure?

- A. Increase the cache TTL
- B. Use Redis with Multi-AZ automatic failover, add jitter to TTLs, and coalesce requests on a miss so one query populates the cache
- C. Move the cache to a public subnet
- D. Switch from Redis to Memcached

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Failover removes the single point of failure, while TTL jitter and request coalescing prevent a synchronized rush of identical queries to the database.

**Q5.** A team invalidates `/*` on the CloudFront distribution after every deployment. What is the better approach?

- A. Reduce the default TTL to zero
- B. Disable caching for the affected behavior
- C. Use versioned object names so new deployments produce new cache keys, allowing long TTLs and no invalidation
- D. Move to a lower price class

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Versioned filenames make each release a distinct object, so old objects expire naturally and nothing needs invalidating.

**Q6.** A media company streams video over a protocol that is not HTTP and needs traffic routed onto the AWS backbone as early as possible. Which service applies?

- A. Amazon CloudFront
- B. AWS Global Accelerator
- C. CloudFront Functions
- D. Amazon Route 53 latency routing

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* CloudFront handles HTTP and HTTPS only; Global Accelerator supports TCP and UDP and moves traffic onto the AWS network at the nearest edge, as covered in section 23.6.
