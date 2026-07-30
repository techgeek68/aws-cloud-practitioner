# Chapter 30: Cost-Optimized Architectures and Exam Readiness

---

Design Cost-Optimized Architectures is 20% of SAA-C03. Section 10.6 covered the four pillars of EC2 cost optimization and Chapter 15 covered the billing tools. This chapter is about cost as an architectural property rather than a monthly review.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 30.1 Cost-Aware Design Decisions

The decisions with the largest bill impact are made at design time, not at review time.

| Decision | Consequence |
| --- | --- |
| Managed service or self-managed | Removes or retains staff effort, which usually outweighs the price difference |
| Serverless or always-on | Determines whether idle costs anything |
| Multi-Region or multi-AZ | Roughly doubles infrastructure plus cross-Region transfer |
| Synchronous or asynchronous | Synchronous requires capacity for peak; a queue lets you provision for average |
| Where data crosses a boundary | Every Availability Zone and Region boundary has a transfer charge |
| Storage class and lifecycle | Determines whether cold data costs the same as hot data |
| Instance family | The wrong family cannot be fixed by resizing it |

**The order of magnitude rule.** Right-sizing typically saves tens of percent. Commitment pricing saves tens of percent. Changing the architecture, such as replacing an always-on fleet with serverless for a spiky workload, or replacing a self-managed cluster with a managed service, can save an order of magnitude. Exhaust the architectural options before optimizing what should not exist.

**Cost per unit of business value.** Total spend rising is not itself a problem if it is rising more slowly than revenue. Cost per transaction, per user, or per gigabyte processed is the metric worth tracking, because it distinguishes growth from waste.

---

## 30.2 Compute Cost Optimization

**In order of typical impact**

1. **Turn off what is not used.** Non-production environments running outside business hours are pure waste. An instance running 168 hours a week that only needs 50 costs three times what it should.
2. **Right-size.** Use Compute Optimizer, and change family before size when the constraint is memory or network rather than CPU, per section 19.7.
3. **Use Graviton.** Better price performance for most workloads, with architecture compatibility as the only real constraint.
4. **Commit.** Compute Savings Plans for the steady baseline, after right-sizing rather than before.
5. **Use Spot for interruptible work**, diversified across pools per section 19.3.
6. **Scale to demand.** An Auto Scaling group tracking load costs less than a fleet sized for peak.
7. **Move to serverless where the workload is intermittent**, since idle Lambda costs nothing.

**Container-specific**

- Fargate removes instance management but costs more per unit of compute than a well-utilized EC2 instance. It wins when utilization would otherwise be low or variable.
- Fargate Spot for interruptible tasks.
- Right-size task CPU and memory requests. Over-requesting wastes capacity on every task, multiplied by the task count.

**Lambda-specific**

- Memory is the CPU dial, so a higher memory setting can be cheaper when it reduces duration by more than it raises the per-millisecond rate. Measure with Lambda Power Tuning.
- Provisioned concurrency bills for readiness whether invoked or not. Use it only where cold start latency is a stated requirement.
- Graviton functions cost less per GB-second.

---

## 30.3 Storage and Data Transfer Cost Optimization

**Storage**

- **Lifecycle policies** on every bucket holding data that ages, plus a rule aborting incomplete multipart uploads, per section 18.3.
- **Intelligent-Tiering** where the access pattern is unknown, since it has no retrieval charges.
- **gp3 over gp2** for EBS: cheaper per GB with a better baseline, and IOPS provisioned independently of size.
- **Delete orphans.** Unattached EBS volumes, snapshots of deleted volumes, and AMIs whose snapshots persist after deregistration all bill indefinitely.
- **Snapshot lifecycle** through Amazon Data Lifecycle Manager or AWS Backup, so snapshots are pruned rather than accumulating.
- **EFS lifecycle management** to move untouched files to Infrequent Access and Archive.

**Data transfer, which architects most often miss**

| Path | Charge |
| --- | --- |
| Inbound from the internet | Free |
| Outbound to the internet | Charged, tiered |
| Between Availability Zones in a Region | Charged in both directions |
| Between Regions | Charged |
| Within an Availability Zone using private IPs | Free |
| To Amazon S3 in the same Region | Free |
| Through a NAT gateway | Charged per GB processed, on top of the hourly rate |
| Through a VPC gateway endpoint | Free |
| Out through Amazon CloudFront | Charged, but usually less than direct egress, and cache hits do not touch the origin |

**Practical reductions**

1. **Gateway endpoints for S3 and DynamoDB** in every VPC, which removes NAT processing charges for that traffic.
2. **CloudFront in front of anything served over HTTP**, which reduces both egress cost and origin load.
3. **Keep chatty traffic within an Availability Zone** where the architecture allows, since cross-AZ transfer is charged both ways.
4. **Interface endpoints** where AWS service traffic volume exceeds the endpoint's hourly cost.
5. **Compress responses**, which reduces egress directly.

The instinct to spread everything across zones for resilience is correct, but a chatty microservice mesh distributed across three zones can generate significant cross-AZ charges. Distribute for availability; keep high-volume internal chatter local where you can.

---

## 30.4 Managed Services as Cost Optimization

The price comparison people make is wrong. Comparing an RDS instance-hour against an EC2 instance-hour ignores the reason RDS exists.

**What a managed service removes**

- Patching, upgrades, and the testing around them.
- Backup configuration, monitoring, and restore testing.
- High availability engineering and failover testing.
- The on-call burden when it breaks at 3am.

**A worked comparison.** A self-managed PostgreSQL cluster on EC2 might cost 30% less in instance-hours than the equivalent RDS Multi-AZ deployment. If it consumes one day a month of an engineer's time, the saving disappears at any realistic loaded cost, before counting the outage that happens when the person who understood it leaves.

**Where self-managed is genuinely right:** an engine or version the managed service does not offer, a required OS-level extension or agent, extreme scale where the percentage difference is large in absolute terms, or an existing team whose specialism is exactly this.

**The same reasoning applies elsewhere.** Managed NAT gateway versus NAT instance, EKS versus self-managed Kubernetes, Amazon MQ versus self-hosted RabbitMQ, OpenSearch Service versus self-managed Elasticsearch. In each case the cheaper option on paper is more expensive in practice unless the team is already doing that work well.

---

## 30.5 Cost Governance at Scale

**Visibility first.** Nothing is optimized that cannot be attributed.

- **Cost allocation tags**, activated in the billing console and applied consistently. Tag policies in AWS Organizations enforce the keys and capitalization.
- **AWS Organizations** for consolidated billing, aggregated volume tiers, and shared reservations, per section 15.4.
- **Account structure as a cost boundary**, giving each team or environment its own account, which makes attribution exact rather than tag-dependent.

**Controls**

- **AWS Budgets** with alerts at percentage thresholds, and budget actions that can apply a restrictive policy or stop resources when a threshold is crossed.
- **Cost Anomaly Detection**, configured before it is needed, which catches the misconfigured job that runs all weekend.
- **Service control policies** restricting expensive instance families or Regions the business does not operate in.
- **Service quotas** as a blast radius limit, since a quota nobody needs raised is also a spending cap.

**Review cadence.** Someone owns the monthly review, works the Cost Explorer right-sizing and commitment recommendations, checks Trusted Advisor cost checks, and closes the loop on anomalies. Without a named owner, none of it happens twice.

**Showback and chargeback.** Showback reports what each team spends. Chargeback bills it to them. Showback changes behavior at a fraction of the political cost, and is usually the right place to start.

---

## 30.6 Exam Strategy

**Mechanics.** 65 questions, 130 minutes, two minutes each. Multiple choice with one correct answer, and multiple response where the question states how many to select.

**A method that works**

1. **Read the final sentence first.** It states the actual question, which is often narrower than the scenario implies.
2. **Identify the qualifying constraint.** Nearly every question has one: *most cost-effective*, *least operational overhead*, *minimum downtime*, *highest availability*, *without changing the application*, *fewest changes*. It usually eliminates two options.
3. **Eliminate what does not work.** Typically one option is technically wrong rather than merely worse.
4. **Choose on the constraint,** not on which answer is most sophisticated.

**Distractor patterns**

- Correct, but requires managing servers, when the question asked for least operational overhead.
- Correct at a different scale, such as multi-Region offered where multi-AZ satisfies the requirement.
- The right service family, wrong member: a read replica where automatic failover was needed, or SNS where a durable queue per consumer was needed.
- A real service doing something it does not do.
- Two options that are nearly identical, differing in one word. That word is the answer.

**Timing.** Flag anything over three minutes and move on. Answer everything, since unanswered scores zero and a considered guess between two remaining options is better than a blank.

**In the final week.** Work the checklist in section 30.7, do the practice set in 30.8, and revisit the topics that recur: shared responsibility, Multi-AZ versus read replicas, policy evaluation, storage class selection, decoupling service selection, and the DR strategies.

---

## 30.7 Domain-by-Domain Review Checklist

**Domain 1: Design Secure Architectures, 30%**

- Explicit deny always wins; boundaries and SCPs cap rather than grant.
- Same account: identity or resource policy suffices. Cross-account: both required.
- Roles for workloads, never access keys on instances or in code.
- External ID for third parties assuming a role in your account.
- IAM Identity Center for workforce, Cognito for application users.
- KMS key policies are mandatory and primary; an IAM allow alone is not enough.
- S3 Block Public Access at account level; CloudFront with OAC for public content.
- Encryption cannot be added to an existing unencrypted RDS instance.
- Organization CloudTrail trail to a separate logging account.

**Domain 2: Design Resilient Architectures, 26%**

- Multi-AZ by default; multi-Region only when a stated requirement demands it.
- Multi-AZ is availability; read replicas are read scaling. Not interchangeable.
- Enable ELB health checks on Auto Scaling groups.
- Static stability: provision so a zone loss needs no scaling action.
- SQS visibility timeout must exceed maximum processing time.
- Dead-letter queues on every production queue.
- Replication propagates deletion; backups and versioning are the defense.
- Know the four DR strategies and their RTO, RPO, and cost ordering.
- Route 53 failover requires a health check on the primary.

**Domain 3: Design High-Performing Architectures, 24%**

- Match instance family to the actual bottleneck, not to CPU by default.
- Placement groups: cluster for latency, spread for isolation, partition for distributed data platforms.
- Caching layers: edge, application, database. Cache closest to the user first.
- CloudFront cache key design determines hit ratio.
- DynamoDB partition key cardinality determines whether you get throttled.
- Kinesis for streams with replay and multiple consumers; SQS for work items processed once.
- Global Accelerator for non-HTTP and fast failover; CloudFront for cacheable HTTP.
- Athena for infrequent queries, Redshift for repeated complex analytics.

**Domain 4: Design Cost-Optimized Architectures, 20%**

- Right-size before committing, or the commitment locks in the waste.
- Compute Savings Plans cover EC2, Lambda, and Fargate.
- Spot for interruptible work, diversified across pools.
- Gateway endpoints for S3 and DynamoDB remove NAT processing charges.
- Storage class selection and lifecycle rules, including aborting incomplete multipart uploads.
- Cross-AZ data transfer is charged in both directions.
- Managed services usually cost less in total than the self-managed equivalent.
- Cost allocation tags and account structure make attribution possible.

---

## 30.8 Full-Length Practice Set

Twenty scenario questions across the four domains. This section is a deliberate exception to the usual three to five per chapter.

**Q1.** An application in account A must read from an S3 bucket in account B. The identity policy in A allows `s3:GetObject`. Requests still fail. What is missing?

A. An SCP in account A
B. A bucket policy in account B allowing the principal from account A
C. A VPC endpoint for S3
D. Encryption on the bucket

**Answer: B.** Cross-account access requires both the caller's identity policy and the resource policy to allow it.

**Q2.** A company needs 15-minute recovery in a second Region for a relational workload, with data loss measured in seconds. Which option fits?

A. Automated backups copied cross-Region
B. Aurora Global Database with a secondary Region
C. A cross-Region read replica on RDS for MySQL
D. AWS Backup with a daily plan

**Answer: B.** Aurora Global Database replicates with typical lag under a second and promotes in about a minute.

**Q3.** An Auto Scaling group launches instances that pass status checks but serve HTTP 502 errors. What should be changed?

A. Increase the desired capacity
B. Enable ELB health checks on the group
C. Change to step scaling
D. Reduce the health check grace period

**Answer: B.** EC2 status checks confirm the instance runs, not that the application responds.

**Q4.** A queue-based worker occasionally processes the same message twice. Processing takes up to 2 minutes; the visibility timeout is 60 seconds. What is the fix?

A. Switch to a FIFO queue
B. Increase the visibility timeout beyond the maximum processing time
C. Enable long polling
D. Add a dead-letter queue

**Answer: B.** The message becomes visible again before processing finishes, so another consumer collects it.

**Q5.** Instances in private subnets download 20 TB per month from Amazon S3. Which change reduces cost most?

A. Move the instances to public subnets
B. Create a gateway VPC endpoint for S3
C. Enable S3 Transfer Acceleration
D. Use a NAT instance instead of a NAT gateway

**Answer: B.** Gateway endpoints carry no hourly or per-GB charge and remove the traffic from the NAT path.

**Q6.** A team must give a third-party vendor access to assume a role in their account. Which condition prevents the confused deputy problem?

A. `aws:SourceIp`
B. `sts:ExternalId`
C. `aws:SecureTransport`
D. `aws:PrincipalOrgID`

**Answer: B.** A unique external ID per customer stops one customer inducing the vendor to act against another.

**Q7.** A workload requires shared file storage accessible from Windows EC2 instances using SMB with Active Directory permissions. Which service?

A. Amazon EFS
B. Amazon FSx for Windows File Server
C. Amazon S3 with Mountpoint
D. Amazon EBS Multi-Attach

**Answer: B.** EFS supports NFS only and does not work with Windows.

**Q8.** An API endpoint's Lambda function does nothing but write the request body to a DynamoDB table. How can this be simplified?

A. Increase function memory
B. Use an API Gateway AWS service integration to write to DynamoDB directly
C. Add provisioned concurrency
D. Replace DynamoDB with RDS

**Answer: B.** API Gateway can call AWS services directly, removing a function that adds cost and latency without doing work.

**Q9.** A company runs 40 VPCs across 12 accounts and needs full connectivity with segmentation between environments. What should be used?

A. VPC peering in a full mesh
B. AWS Transit Gateway with multiple route tables, shared through AWS RAM
C. PrivateLink between each pair
D. Site-to-Site VPN between VPCs

**Answer: B.** A 40-VPC mesh requires 780 peering connections; Transit Gateway route tables provide segmentation.

**Q10.** Data must be retained for seven years and cannot be deleted by anyone, including an administrator, before then. What should be configured?

A. Versioning with MFA delete
B. A bucket policy denying delete actions
C. S3 Object Lock in compliance mode, enabled at bucket creation
D. Cross-Region Replication

**Answer: C.** Compliance mode cannot be overridden by any principal, including root, until retention expires.

**Q11.** An analytics team queries a 40 TB S3 data lake a few times a week and wants no idle infrastructure cost. Which service?

A. Amazon Redshift provisioned
B. Amazon EMR long-running cluster
C. Amazon Athena
D. Amazon RDS

**Answer: C.** Athena is serverless and billed per query, so infrequent use costs nothing between queries.

**Q12.** A three-tier application must scale its web tier automatically while keeping user sessions available if an instance is terminated. What should be done?

A. Enable sticky sessions on the load balancer
B. Store sessions in ElastiCache for Redis or DynamoDB, making the tier stateless
C. Increase the instance size
D. Use a Network Load Balancer

**Answer: B.** Externalizing session state is what makes instances disposable; sticky sessions still lose the session when the target dies.

**Q13.** A Lambda function consuming a DynamoDB stream stops processing after encountering a malformed record. What should be configured?

A. Increase the batch size
B. A maximum retry count, maximum record age, and an on-failure destination
C. Provisioned concurrency
D. A larger memory allocation

**Answer: B.** Without these, a poison record blocks its shard until the record expires.

**Q14.** A media company streams over UDP and requires regional failover in seconds with static IP addresses. Which service?

A. Amazon CloudFront
B. Route 53 latency routing
C. AWS Global Accelerator
D. Application Load Balancer

**Answer: C.** CloudFront handles HTTP only; Global Accelerator supports UDP and provides static anycast IPs unaffected by DNS caching.

**Q15.** An organization must apply a standard set of Config rules and IAM roles to every existing and future account. What should be used?

A. A CloudFormation stack per account
B. CloudFormation StackSets with automatic deployment to an organizational unit
C. Nested stacks
D. Terraform applied manually per account

**Answer: B.** StackSets deploy across accounts and Regions and enroll new accounts joining an OU.

**Q16.** A DynamoDB table using a low-cardinality partition key is throttled while total consumed capacity is well below provisioned. Why?

A. On-demand mode is required
B. Traffic concentrates on a small number of partitions
C. The item size exceeds 400 KB
D. A local secondary index is missing

**Answer: B.** Capacity is distributed across partitions; a low-cardinality key creates a hot partition.

**Q17.** A production RDS instance is unencrypted and must now be encrypted at rest. What is the correct path?

A. Modify the instance and enable encryption
B. Snapshot, copy the snapshot with encryption enabled, restore from the encrypted copy
C. Create an encrypted read replica and promote it
D. Enable encryption on the DB subnet group

**Answer: B.** Encryption cannot be enabled on an existing instance.

**Q18.** A batch job runs for six hours nightly, tolerates interruption, and must minimize cost. Which compute option?

A. On-Demand EC2 in one instance type
B. Reserved Instances
C. Spot Instances diversified across instance types and zones, with capacity-optimized allocation
D. AWS Lambda

**Answer: C.** Lambda's 15-minute ceiling rules it out; diversified Spot minimizes both cost and interruption risk.

**Q19.** A CloudFront distribution has a 4% cache hit ratio. The cache behavior forwards all cookies and query strings. What should change?

A. Increase the maximum TTL only
B. Restrict the cache key to elements that actually vary the response
C. Add a second origin
D. Switch to a lower price class

**Answer: B.** Everything in the cache key fragments the cache, making nearly every request unique.

**Q20.** A company wants one member account to manage GuardDuty findings across the organization without granting it administrative access to the other accounts. What supports this?

A. A service control policy
B. Delegated administrator
C. Consolidated billing
D. Cross-account IAM roles in every account

**Answer: B.** Delegated administrator assigns organization-wide control of one specific service to a member account.

---

## 30.9 Closing Part III

Part III has covered the four SAA-C03 domains through design decisions rather than service descriptions. The recurring lesson is that almost every question has a stated constraint, and the constraint decides the answer among options that would all technically work.

Part IV turns from designing systems to operating them, which is the work an entry-level cloud engineer actually does day to day.
