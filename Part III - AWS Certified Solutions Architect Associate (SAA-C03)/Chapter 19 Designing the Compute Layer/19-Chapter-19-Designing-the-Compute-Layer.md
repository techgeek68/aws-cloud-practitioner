# Chapter 19: Designing the Compute Layer

---

Chapter 10 covered what EC2, containers, and Lambda are. This chapter covers choosing between them and sizing what you choose. Instance lifecycle, pricing model definitions, and the four pillars of EC2 cost optimization are in Chapter 10 and are not repeated.

[The source repository for Part III ends after the storage chapter. This chapter and those following are written to the SAA-C03 exam guide domains and verified against AWS documentation.]

---

## 19.1 Selecting an Instance Family

Instance type naming decodes as family, generation, optional capability letters, and size: `m6gd.large` is general purpose, sixth generation, Graviton, with local NVMe storage, at large size.

| Workload profile | Family | Signal in the question |
| --- | --- | --- |
| Balanced, unknown, or general web and application servers | M | No stated bottleneck |
| Low average with occasional bursts, development and test | T | Small, intermittent, cost-sensitive |
| CPU-bound: batch, encoding, gaming servers, HPC | C | High compute, modest memory |
| Memory-bound: in-memory caches, large databases, real-time analytics | R, X, Z | Large working set, memory ratios named |
| Storage-bound: NoSQL, data warehouses, distributed file systems | I, D, H | High local IOPS or sequential throughput |
| GPU or accelerator: ML training and inference, graphics | P, G, Inf, Trn | Training, inference, rendering |

**Burstable T instances.** These accrue CPU credits while below baseline and spend them above it. Standard mode throttles to baseline when credits are exhausted; unlimited mode continues at full speed and bills for the surplus. T instances are the wrong answer for anything with sustained high CPU, and a common distractor in questions describing steady load.

**Graviton.** AWS's Arm-based processors, denoted by a `g` in the family name, offer better price performance than equivalent x86 instances for most workloads. The constraint is architecture: software must be available for Arm, which is straightforward for interpreted languages and managed services and less so for commercial binaries. When a question asks for the most cost-effective compute and does not name an x86 dependency, Graviton is usually part of the answer.

**Sizing within a family.** Doubling the size doubles vCPU, memory, and network allocation, and doubles the price. There is rarely a discount for going larger, so two smaller instances usually cost the same as one larger one while providing redundancy. That is an argument for horizontal scaling that has nothing to do with elasticity.

**Network and EBS bandwidth scale with size.** A `t3.micro` cannot saturate an EBS volume that a `c6i.4xlarge` can. When a question describes storage performance below expectations on a small instance, the instance is the bottleneck, not the volume.

---

## 19.2 Placement Groups and Tenancy

**Placement groups** control how instances are physically positioned relative to one another.

| Type | Placement | Use | Trade-off |
| --- | --- | --- | --- |
| Cluster | Packed close together in one Availability Zone | HPC and tightly coupled applications needing lowest latency and highest throughput between nodes | No fault isolation; a rack failure can take all of them |
| Spread | Each instance on distinct underlying hardware, across up to seven per Availability Zone | Small numbers of critical instances that must not share a failure domain | Limited instance count |
| Partition | Grouped into partitions, each on separate racks | Large distributed systems such as HDFS, Cassandra, and Kafka that are partition-aware | Requires the application to understand partitions |

The exam signal: **cluster for performance, spread for isolation of a few instances, partition for large replicated data platforms.**

**Tenancy**

- **Shared**, the default, places instances on hardware shared with other AWS customers.
- **Dedicated Instances** run on hardware not shared with other accounts.
- **Dedicated Hosts** give a whole physical server with visibility of sockets and cores.

The distinction that decides questions: only **Dedicated Hosts** expose physical socket and core counts, which is what per-socket or per-core software licensing requires. If a question mentions bring-your-own-license for Oracle, Windows Server, or SQL Server, the answer is Dedicated Hosts.

---

## 19.3 Designing with Spot and Savings Plans

**Spot in an architecture**

Spot Instances use spare capacity at up to 90% off On-Demand and are reclaimed with a two-minute interruption notice. Designing for them means:

- The workload tolerates interruption: batch processing, CI builds, rendering, stateless web tiers behind a load balancer, and containerized tasks.
- State lives somewhere else, so an interrupted instance loses nothing.
- **Diversify across instance types and Availability Zones.** Spot capacity is per instance type per zone, so a fleet requesting six types across three zones is far less likely to be fully interrupted than one requesting a single type.
- **Capacity-optimized allocation** in an EC2 Fleet or Auto Scaling group selects pools with the deepest spare capacity, which reduces interruption frequency more than price-optimized selection.
- **Handle the interruption notice.** The instance metadata endpoint exposes a termination notice two minutes ahead. Use it to drain connections and checkpoint work.

**A mixed instances policy** in an Auto Scaling group is the standard production pattern: a base of On-Demand instances covering the minimum the service must always have, with the remainder filled from Spot. This is the answer when a question wants cost reduction without accepting the risk of total capacity loss.

**Choosing a commitment**

| Situation | Commitment |
| --- | --- |
| Steady baseline, instance family may change over time | Compute Savings Plan |
| Steady baseline on a known family in a known Region | EC2 Instance Savings Plan, deeper discount, less flexibility |
| Capacity must be guaranteed in a specific Availability Zone | On-Demand Capacity Reservation, optionally combined with a Savings Plan |
| Steady baseline, and the workload includes Lambda or Fargate | Compute Savings Plan, which covers all three |
| Interruptible, flexible, time-insensitive | Spot |

**Capacity Reservations are not a discount.** They reserve capacity in a zone and bill whether used or not. Pairing one with a Savings Plan gets both the guarantee and the price reduction. Questions mentioning a guaranteed capacity requirement for a disaster recovery zone or a scheduled event want a Capacity Reservation.

---

## 19.4 AMI and Golden Image Strategy

**The two approaches**

- **Golden image.** Bake the operating system, runtime, agents, and application into an AMI. Instances launch ready to serve, so boot is fast and consistent, which matters when Auto Scaling must add capacity quickly.
- **Bootstrapping.** Launch a base AMI and configure it at boot with user data or a configuration management tool. Slower to become healthy, but changes do not require rebuilding an image.

Most production designs use both: a golden image containing everything slow to install, with user data supplying the small amount of environment-specific configuration.

**EC2 Image Builder** automates image creation, patching, testing, and distribution on a schedule, producing versioned images and sharing them across accounts and Regions. It is the answer when a question describes maintaining hardened, patched images across an organization.

**Practical points**

- AMIs are Regional. Multi-Region designs must copy them, and the copy has a different AMI ID, which is why templates should look the AMI up by name or tag rather than hardcoding an ID.
- Encrypted AMIs can be shared, but the KMS key must also be shared.
- Deregistering an AMI does not delete its snapshots. This is a recurring source of unexplained EBS spend.
- Boot time is part of the scaling response. An image taking four minutes to become healthy makes an Auto Scaling group four minutes slower to respond, whatever the alarm period.

---

## 19.5 Container Architecture Choices

| Option | You manage | Choose when |
| --- | --- | --- |
| ECS on EC2 | The container instances, their patching, and their scaling | You need control of the instance type, want to use Spot or Reserved capacity directly, or need GPU or specialized hardware |
| ECS on Fargate | Nothing below the task | Operational simplicity matters more than per-unit cost, or the workload is spiky |
| EKS on EC2 | The nodes, plus Kubernetes itself above the managed control plane | Kubernetes is already the standard, or portability across clouds is a requirement |
| EKS on Fargate | Nothing below the pod | Kubernetes API is required but node management is not wanted |

**ECS or EKS.** ECS is simpler, integrates natively with IAM, ALB, and CloudWatch, and has no control plane charge. EKS brings the Kubernetes ecosystem and portability, at the cost of a control plane charge and considerably more operational surface. For a question describing a team with no Kubernetes experience wanting to run containers with minimal overhead, ECS on Fargate is the answer. For a question mentioning existing Kubernetes manifests, Helm charts, or multi-cloud portability, it is EKS.

**Fargate cost profile.** Fargate bills per vCPU-second and GB-second of the task's requested size, with no instance to keep busy. It is more expensive per unit of compute than a well-utilized EC2 instance and cheaper than a poorly utilized one. The break-even sits around the point where instance utilization would be low or highly variable.

**Task placement and networking**

- **awsvpc network mode** gives each task its own elastic network interface and security group, which is what allows per-task network isolation. It also consumes IP addresses from the subnet, which needs planning in a large cluster.
- **Task role versus task execution role.** The task role is what the application uses to call AWS services. The execution role is what the ECS agent uses to pull the image from ECR and write logs. They are separate, and conflating them is a common design error.

**Scaling containers.** ECS Service Auto Scaling and Kubernetes Horizontal Pod Autoscaler scale tasks or pods. On EC2 launch types, the underlying instances also need scaling, through an Auto Scaling group with ECS capacity providers or Karpenter on EKS. Forgetting the second layer produces a service that cannot scale because there is nowhere to place new tasks.

---

## 19.6 Serverless Compute Design

**Where Lambda fits.** Event-driven work, short-lived tasks, glue between services, and APIs with variable or unpredictable traffic. Its economics are best when the workload is intermittent, because idle costs nothing.

**Where it does not.** Anything exceeding 15 minutes, requiring a persistent connection, needing more than 10,240 MB of memory, sensitive to cold start latency in the single-digit milliseconds, or running continuously at high volume, where an always-on container is usually cheaper.

**Concurrency**

- **Reserved concurrency** caps how many concurrent executions a function may use, which protects downstream systems and stops one function consuming the account's whole limit.
- **Provisioned concurrency** keeps a number of execution environments initialized, removing cold starts for latency-sensitive functions. It bills for that readiness whether invoked or not.
- The account-level default is 1,000 concurrent executions per Region, and it is a soft limit.

**Cold starts.** Caused by initializing a new execution environment. Reduced by smaller deployment packages, lighter runtimes, and moving initialization outside the handler so it is reused across invocations. Eliminated for a fixed number of environments by provisioned concurrency.

**Lambda in a VPC.** A function attached to a VPC gets an elastic network interface and can reach private resources. It loses internet access unless the subnet routes through a NAT gateway, and it needs VPC endpoints to reach AWS services privately. Attach a function to a VPC only when it must reach something private, because it adds cost and complexity for no benefit otherwise.

**The database connection problem.** Lambda scales to many concurrent executions, each potentially opening a database connection, which exhausts a relational database's connection limit. **Amazon RDS Proxy** pools and reuses connections and is the standard answer. DynamoDB, being an HTTP API with no persistent connections, does not have this problem, which is one reason it pairs naturally with Lambda.

**Memory is the performance dial.** CPU is allocated in proportion to memory, so a function that is CPU-bound often runs faster and cheaper at higher memory, because the reduction in duration outweighs the higher per-millisecond rate. **AWS Lambda Power Tuning** measures this rather than guessing.

**Other serverless compute**

- **AWS Fargate** for containers with no instance management, covered above.
- **AWS Batch** for large batch workloads, provisioning and scaling compute including Spot automatically.
- **AWS Step Functions** for orchestrating long-running workflows that exceed a single Lambda's duration, covered in section 25.5.

---

## 19.7 Right Sizing with Data

Sizing decisions should come from measurement.

**AWS Compute Optimizer** analyzes CloudWatch metrics and recommends instance types, Auto Scaling group configurations, EBS volume settings, and Lambda memory. Enabling memory metrics through the CloudWatch agent significantly improves its accuracy, because without them it cannot see memory pressure.

**What to look at**

| Metric | Indicates |
| --- | --- |
| `CPUUtilization` sustained below 10% | Overprovisioned, or the wrong family |
| `CPUCreditBalance` trending to zero on a T instance | Wrong family for a sustained workload |
| Memory utilization near capacity, CPU low | Move to a memory-optimized family rather than a larger general purpose one |
| `NetworkIn` and `NetworkOut` near the instance ceiling | Network is the bottleneck; a larger size or a network-optimized type is needed |
| EBS `VolumeQueueLength` consistently high | Storage is the bottleneck, not compute |

**A right-sizing sequence**

1. Collect at least two weeks of metrics, covering a full business cycle.
2. Identify the actual constraint: CPU, memory, network, or storage.
3. Change family before changing size, since the wrong family cannot be fixed by scaling it.
4. Change one variable at a time and remeasure.
5. Apply commitment pricing only after sizing settles, because a Savings Plan bought against an oversized fleet locks in the waste.

That last point is the one people get wrong. Right-size first, commit second.

---

## 19.8 End-of-Chapter Questions

**Q1.** An HPC application requires the lowest possible network latency and highest throughput between its nodes. Which placement group type should be used?

- A. Spread
- B. Partition
- C. Cluster
- D. Dedicated Host

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Cluster placement packs instances close together in one Availability Zone for maximum network performance, accepting the loss of fault isolation.

**Q2.** A company must run Oracle Database under a license tied to physical CPU sockets. Which EC2 option satisfies the licensing requirement?

- A. Dedicated Instances
- B. Dedicated Hosts
- C. Reserved Instances
- D. A cluster placement group

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Only Dedicated Hosts expose physical socket and core counts, which per-socket licensing requires; Dedicated Instances provide isolated hardware without that visibility.

**Q3.** A Lambda function attached to a VPC opens a connection to an RDS database on each invocation. Under load, the database rejects connections. What is the most appropriate fix?

- A. Increase the Lambda function's memory allocation
- B. Increase the RDS instance size
- C. Use Amazon RDS Proxy to pool and reuse database connections
- D. Enable provisioned concurrency on the function

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* RDS Proxy exists specifically to solve connection exhaustion from highly concurrent serverless callers; resizing the database treats the symptom and provisioned concurrency addresses cold starts, not connections.

**Q4.** A batch workload runs nightly, tolerates interruption, and must minimize cost. Which approach is most appropriate?

- A. On-Demand Instances in a single instance type
- B. Reserved Instances for the nightly window
- C. Spot Instances diversified across several instance types and Availability Zones, with capacity-optimized allocation
- D. Dedicated Hosts

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Diversifying Spot across pools and using capacity-optimized allocation minimizes both cost and interruption risk for interruption-tolerant work.

**Q5.** A team with no Kubernetes experience needs to run a containerized web application with the least operational overhead. Which option fits best?

- A. Amazon EKS with self-managed nodes
- B. Amazon ECS with the EC2 launch type
- C. Amazon ECS with AWS Fargate
- D. Amazon EC2 with Docker installed manually

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Fargate removes instance management entirely, and ECS avoids the operational surface and control plane charge that Kubernetes brings to a team not already using it.

**Q6.** An Auto Scaling group takes six minutes to serve traffic from a newly launched instance, because the AMI installs and configures the application at boot. What change most improves the scaling response time?

- A. Reduce the CloudWatch alarm evaluation period
- B. Bake the application into a golden AMI so instances launch ready to serve
- C. Increase the health check grace period
- D. Move to a larger instance type

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Boot time is part of the scaling response; shortening the alarm period does not help if the instance still takes six minutes to become healthy.
