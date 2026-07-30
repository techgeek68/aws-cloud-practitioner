# AWS Cloud Course: From Cloud Fundamentals to Entry Level Cloud Engineer

---
## Course Map

| Part | Title | Chapters |
| ---- | ----- | -------- |
| I | Introduction to Cloud Computing | 1 to 3 |
| II | AWS Certified Cloud Practitioner (CLF-C02) | 4 to 15 |
| III | AWS Certified Solutions Architect Associate (SAA-C03) | 16 to 30 |
| IV | Entry-Level AWS Cloud Engineer Job Skills | 31 to 38 |

---
## Certification Targets

Verified against the official exam guides published on docs.aws.amazon.com and the certification pages on aws.amazon.com.

---
**AWS Certified Cloud Practitioner (CLF-C02)**

- Domain 1: Cloud Concepts, 24% of scored content
- Domain 2: Security and Compliance, 30%
- Domain 3: Cloud Technology and Services, 34%
- Domain 4: Billing, Pricing, and Support, 12%
- 50 scored questions plus 15 unscored questions, multiple choice and multiple response
- Scaled score 100 to 1,000, minimum passing score 700
- [Duration is widely reported as 90 minutes but is not stated in the exam guide PDF; confirm on the AWS certification page when booking.]

---
**AWS Certified Solutions Architect Associate (SAA-C03)**

- Domain 1: Design Secure Architectures, 30% of scored content
- Domain 2: Design Resilient Architectures, 26%
- Domain 3: Design High-Performing Architectures, 24%
- Domain 4: Design Cost-Optimized Architectures, 20%
- 65 questions, multiple choice and multiple response, 130 minutes
- Scaled score 100 to 1,000
- [The published minimum passing score of 720 could not be confirmed in the current official exam guide; confirm on the AWS certification page.]

---
Exam fees are deliberately omitted because they vary by region and change without notice. Check the AWS certification page at booking time.
---

## How This Course Works

- **Heading levels.** Every chapter uses the same three levels: chapter title, numbered section, numbered subsection. Labs use numbered steps only.

- **One definition, one place.** Each concept is taught once under its correct parent topic. Later chapters reference it rather than repeating it. Where two parts touch the same service, the syllabus states which chapter owns the definition and which applies it.

- **Labs.** Every lab is a numbered step-by-step procedure with console paths, commands, verification, and a full cleanup section. Original screenshots stay attached to the step they illustrate.

- **End of chapter questions.** Every chapter closes with 3 to 5 high-probability exam questions, each labeled AWS Certified Cloud Practitioner or AWS Certified Solutions Architect - Associate, with the answer and a one-line explanation. This is not repeated in the entries below.

---
## Part I: Introduction to Cloud Computing

### Chapter 1. What Cloud Computing Is

- 1.1 Definition and Core Characteristics: on-demand delivery of compute, storage, database, and other IT resources over the internet with pay-as-you-go pricing.

- 1.2 Infrastructure as Software: how programmable infrastructure replaces manual hardware provisioning, and what that changes for delivery speed.

- 1.3 Traditional Computing Model vs Cloud Computing Model: procurement, capacity planning, maintenance, and time to value, side by side.

- 1.4 Similarities Between AWS and Traditional IT: mapping familiar on-premises components to their cloud equivalents.

- 1.5 The Six Advantages of Cloud Computing: the AWS framing, from trading capital expense for variable expense through to going global in minutes.

- 1.6 Categories of Cloud Services: the ten service families used as the organizing spine of this course, with one representative AWS service each.

### Chapter 2. Cloud Service and Deployment Models

- 2.1 IaaS, Infrastructure as a Service: what the provider manages, what you manage, and typical AWS examples.

- 2.2 PaaS, Platform as a Service: managed runtime and platform layers, and the operational work that disappears.

- 2.3 SaaS, Software as a Service: consuming finished applications, and where responsibility sits.

- 2.4 Comparing the Three Models: the single responsibility table referenced by every later chapter.

- 2.5 Public Cloud: characteristics, fit, and the AWS position.

- 2.6 Private Cloud: on-premises and hosted variants, and the control versus cost trade-off.

- 2.7 Hybrid Cloud: connecting on-premises estates to AWS, and the services that make it work.

- 2.8 Community Cloud: shared-tenancy models for organizations with common regulatory needs.

- 2.9 Choosing a Deployment Model: a short decision guide based on data residency, control, and cost.

### Chapter 3. Cloud Economics

- 3.1 CapEx vs OpEx: the spending shift, and why it changes budgeting and project approval.

- 3.2 Total Cost of Ownership: the cost components on-premises budgets usually miss.

- 3.3 Worked Comparison, On-Premises vs Cloud: an illustrative calculation covering hardware, facilities, power, staffing, and utilization.

- 3.4 Economies of Scale and Utilization: why idle capacity is the largest hidden cost in traditional IT.

- 3.5 Where AWS Pricing Fits: a pointer forward to Chapter 15, which owns AWS-specific pricing, billing, and support content.

---
## Part II: AWS Certified Cloud Practitioner (CLF-C02)

### Chapter 4. Introduction to Amazon Web Services

- 4.1 What Web Services Are: request and response over HTTP, and why AWS is built this way.

- 4.2 What AWS Is: the platform, its scale, and its service breadth.

- 4.3 The CLF-C02 Exam at a Glance: domains, weightings, format, and passing score, from the official exam guide.

- 4.4 Ways to Interact with AWS: Management Console, CLI, SDKs, CloudShell, and infrastructure as code, introduced here and used hands-on from Chapter 7 and Part IV.

- 4.5 The AWS Service Layers: how services stack from infrastructure through platform to applications.

- 4.6 How to Study for This Exam: mapping the four domains onto the chapters of Part II.

### Chapter 5. AWS Global Infrastructure

- 5.1 Regions: what a Region is, how Regions are isolated, and the four criteria for choosing one.

- 5.2 Availability Zones: physical separation, low-latency links, and the Multi-AZ design rule.

- 5.3 Data Centers: what sits inside an Availability Zone, and why AWS does not expose it.

- 5.4 Points of Presence: edge locations and regional edge caches, and the services that use them.

- 5.5 Extended Infrastructure: Local Zones, Wavelength Zones, and AWS Outposts, with the workload each targets.

- 5.6 Regions vs Availability Zones vs Edge Locations: the comparison table referenced throughout the course.

- 5.7 Infrastructure Characteristics: elasticity, scalability, fault tolerance, and high availability, defined once here for use everywhere else.

### Chapter 6. The AWS Frameworks

- 6.1 The AWS Cloud Adoption Framework: purpose, and when an organization uses it.

- 6.2 The Six CAF Perspectives: Business, People, Governance, Platform, Security, Operations, with the foundational capabilities of each.

- 6.3 Cloud Transformation Domains and Outcomes: technology, process, organization, and product.

- 6.4 The AWS Well-Architected Framework: purpose, structure, and the review process.

- 6.5 The Six Pillars: Operational Excellence, Security, Reliability, Performance Efficiency, Cost Optimization, and Sustainability, defined once here. Chapter 16 applies them to design decisions.

- 6.6 General Design Principles: the framework-level principles that cut across all six pillars.

- 6.7 The AWS Well-Architected Tool: running a review against a workload.

### Chapter 7. Getting Access to AWS

- 7.1 Creating an AWS Account: sign-up sequence, the root user, and the first actions to take immediately after creation.

- 7.2 How the AWS Free Tier Works: free tier types, headline limits, and how to monitor usage before it costs money.

- 7.3 Securing the Root User and Enabling MFA: the mandatory hardening steps for a new account.

- 7.4 Sign-In Paths: IAM user for learning and development, IAM Identity Center for production, with the reasoning behind each.

- 7.5 Navigating the Management Console: layout, service search, Region selector, and role switching.

- 7.6 Lab: Create a Key Pair in the Console: numbered steps covering creation, private key handling, verification, and cleanup.

- 7.7 Lab: Create a Security Group in the Console: numbered steps covering inbound rules, outbound rules, instance attachment, verification, and cleanup.

- 7.8 Lab Cost Discipline: setting one budget alert before starting any lab, and the teardown habit applied to every lab in this course. The full billing toolset is covered in Chapter 15.

### Chapter 8. AWS Cloud Security

- 8.1 The Shared Responsibility Model: security of the cloud versus security in the cloud, with scenarios across EC2, RDS, S3, and Lambda.

- 8.2 IAM Components: users, groups, roles, and policies, defined once here for the whole course.

- 8.3 Authentication and Authorization: credential types, MFA, and how a request is evaluated.

- 8.4 Policy Types and Structure: identity-based, resource-based, permission boundaries, and service control policies, with a worked JSON policy.

- 8.5 IAM Roles and Temporary Credentials: why roles beat long-lived access keys, and where AWS STS fits. Chapter 17 covers cross-account and federated role design.

- 8.6 Securing a New AWS Account: the recommended baseline checklist.

- 8.7 AWS Organizations: organizational units, service control policies, and multi-account structure. This chapter owns the definition; Chapter 15 covers consolidated billing only.

- 8.8 Detective Services: CloudTrail, AWS Config, GuardDuty, Inspector, Security Hub, Detective, and Macie, with one line on what each finds.

- 8.9 Protective Services: AWS WAF, AWS Shield, AWS Network Firewall, and AWS Firewall Manager. Chapter 21 places them in a network design.

- 8.10 Protecting Data: encryption at rest and in transit, AWS KMS, CloudHSM, AWS Secrets Manager, and S3 bucket protection controls.

- 8.11 Compliance: AWS Artifact, the compliance programs, and the obligations that remain with the customer.

- 8.12 Lab: Introduction to AWS IAM: numbered steps to create users and groups, attach policies, launch a test host, and validate permissions by sign-in testing.

### Chapter 9. Networking and Content Delivery

- 9.1 Networking Basics: IP addressing, CIDR notation, and subnetting.

- 9.2 Amazon VPC Fundamentals: VPCs, subnets, reserved IP addresses, and public IP address types.

- 9.3 Elastic Network Interfaces and Route Tables: how traffic is attached and directed.

- 9.4 VPC Connectivity Options: internet gateway, NAT gateway, VPC peering, VPC endpoints, Transit Gateway, Site-to-Site VPN, and Direct Connect, named and defined here. Chapters 21 and 22 design with them.

- 9.5 VPC Security Controls: security groups versus network ACLs, and the stateful versus stateless distinction.

- 9.6 Amazon Route 53: hosted zones, record types including alias records, health checks, and the routing policies.

- 9.7 Amazon CloudFront: distributions, origins, cache behavior, and the pricing model.

- 9.8 Lab: Build a VPC and Launch a Web Server: numbered steps covering Region selection, VPC, public and private subnets, internet gateway, Elastic IP and NAT gateway, route tables, security group, key pair, EC2 web server with user data, verification, browser test, and cleanup.

### Chapter 10. Compute

- 10.1 The AWS Compute Portfolio: instances, containers, and serverless, with the selection criteria.

- 10.2 Amazon EC2 Fundamentals: AMIs, instance families and sizes, the instance lifecycle, and instance metadata using IMDSv2.

- 10.3 EC2 Storage Options: instance store versus EBS, and the durability consequence of each.

- 10.4 Elastic IP Addresses: when to use one, and what an idle address costs.

- 10.5 EC2 Pricing Models: On-Demand, Reserved Instances, Savings Plans, Spot, and Dedicated Hosts.

- 10.6 The Four Pillars of EC2 Cost Optimization: right sizing, increasing elasticity, choosing the right pricing model, and optimizing storage. Chapter 30 extends this to whole-architecture cost design.

- 10.7 Containers on AWS: Docker basics, containers versus virtual machines, Amazon ECS and its launch types, Amazon EKS, and Amazon ECR.

- 10.8 AWS Lambda: execution model, event sources, function configuration, default quotas, and use cases.

- 10.9 AWS Elastic Beanstalk: supported platforms, what it provisions on your behalf, and when it is the right answer.

- 10.10 Other Compute Services: AWS Batch, AWS App Runner, and Amazon Lightsail, with the workload each suits.

- 10.11 Lab: Introduction to Amazon EC2: numbered steps to launch an instance with user data, monitor status checks and CloudWatch metrics, review the system log and instance screenshot, open HTTP access, resize the instance, expand the EBS volume, review service quotas, and test and disable stop protection. The seven original screenshots are placed at their matching steps.

- 10.12 Lab: Host a Web App on RHEL with Nginx: numbered steps covering instance launch, SSH access and key permissions, Nginx installation, application file deployment, ownership and permissions, service restart, and browser validation.

- 10.13 Challenge Lab: Voting Application on Nginx: extend the previous lab with a PHP API backend, frontend, and Nginx site configuration.

- 10.14 Lab: Lambda Function Triggered by EventBridge: numbered steps covering the execution role, target EC2 instance, function creation, scheduled rule, code deployment, and verification in CloudWatch Logs, with cleanup.

- 10.15 Lab: Deploy a Web Application with Elastic Beanstalk: numbered steps covering the application package, S3 upload, environment creation, monitoring, verification, exploration of provisioned resources, an optional application update, and cleanup.

### Chapter 11. Storage

- 11.1 The AWS Storage Portfolio: block, object, and file storage, with the decision rule for each.

- 11.2 Amazon EBS: volume types, snapshots, encryption, Multi-Attach, and pricing dimensions.

- 11.3 Amazon S3: buckets, objects, keys, the durability model, bucket URL formats, and consistency behavior.

- 11.4 S3 Storage Classes: the current class list with retrieval characteristics and intended access pattern. This chapter owns the class definitions; Chapter 18 covers selecting between them under cost and performance constraints.

- 11.5 S3 Data Management: versioning, lifecycle policies, replication, Object Lock, and access points.

- 11.6 S3 Glacier Storage Classes: the three archive classes, retrieval options, and lifecycle transitions into them.

- 11.7 Amazon EFS: architecture, mount targets, performance and throughput modes, and storage classes.

- 11.8 Amazon FSx: the file system options, and the workload each serves.

- 11.9 Hybrid and Transfer Services: AWS Storage Gateway, AWS DataSync, AWS Transfer Family, and the AWS Snow Family.

- 11.10 Storage Case Studies: matching a stated requirement to the correct service.

- 11.11 Lab: Amazon EBS: numbered steps to launch an instance, create and attach a volume, format and mount it, take a snapshot, delete data, restore from the snapshot, and clean up.

- 11.12 Lab: Amazon S3: numbered steps covering bucket creation, uploads and folders, permissions, versioning, lifecycle rules, static website hosting, server-side encryption, access logging, cross-Region replication, access points, Object Lock, and cleanup.

- 11.13 Lab: Amazon EFS: numbered steps covering supporting VPC and instances, file system creation, mount targets and security groups, mounting from two instances with the mount helper and with the NFS client, persistent mounts, shared access testing, performance and throughput modes, access points, encryption in transit and at rest, lifecycle management, AWS Backup, CloudWatch monitoring, resource policies, troubleshooting, and full cleanup.

### Chapter 12. Databases

- 12.1 Relational vs Non-Relational Data: the modeling difference that drives service selection.

- 12.2 Amazon RDS: supported engines, Multi-AZ, read replicas, automated backups, and maintenance windows.

- 12.3 Amazon Aurora: architecture, replicas, cluster endpoints, Aurora Serverless v2, and Aurora Global Database.

- 12.4 Amazon DynamoDB: tables, partition and sort keys, capacity modes, secondary indexes, streams, and global tables. Chapter 20 covers key design and access patterns.

- 12.5 Amazon Redshift: cluster architecture, node types, and analytics use cases.

- 12.6 Purpose-Built Database Services: ElastiCache, MemoryDB, Neptune, DocumentDB, Timestream, and Keyspaces, one line each.

- 12.7 Choosing the Right Database: a decision table driven by data shape, access pattern, consistency need, and scale.

- 12.8 Database Case Studies: data protection, migration, and payment-processing scenarios.

- 12.9 Lab: Build a Database Server and Interact with the Database: numbered steps covering the network setup, RDS launch, EC2 web server, application configuration, connection testing, and cleanup.

- 12.10 Challenge Lab: Amazon RDS with a Full-Stack Student App: deploy the Node.js backend and frontend against RDS, configure the security groups, and validate end to end.

### Chapter 13. Elasticity, Load Balancing, and Monitoring

- 13.1 Elastic Load Balancing: Application, Network, and Gateway Load Balancers, with listener, target group, and health check mechanics.

- 13.2 Amazon CloudWatch: metrics, logs, alarms, and dashboards, plus basic versus detailed monitoring. This chapter owns the definitions; Chapter 23 covers observability design and Chapter 34 covers the CLI operations.

- 13.3 Amazon EC2 Auto Scaling: Auto Scaling groups, launch templates, scaling policies, and health check replacement.

- 13.4 AWS Auto Scaling vs EC2 Auto Scaling: what each covers, and when to use which.

- 13.5 Reliability and Availability: availability tiers, the factors that determine them, and how redundancy raises them.

- 13.6 AWS Trusted Advisor: the check categories, and which support plans expose which checks.

- 13.7 AWS Health Dashboard: service health versus account health, and how to act on an event.

- 13.8 Lab: Load Balancing and Auto Scaling: numbered steps covering the VPC and subnets, security groups, the initial EC2 instance and its application files, target group and load balancer, launch template, Auto Scaling group with health checks and scaling policy, deployment verification, CPU load generation, observed scale-out and automatic recovery, and full cleanup.

### Chapter 14. The Wider AWS Service Catalog

- 14.1 Why This Chapter Exists: the CLF-C02 service list reaches well beyond compute, storage, database, and networking.

- 14.2 Analytics: Amazon Athena, AWS Glue, Amazon Kinesis, Amazon EMR, Amazon QuickSight, and Amazon OpenSearch Service. Chapter 28 designs pipelines with them.

- 14.3 AI and Machine Learning: Amazon SageMaker, Amazon Bedrock, Amazon Q, Rekognition, Comprehend, Textract, Transcribe, Translate, Polly, Lex, and Kendra, with the task each performs.

- 14.4 Developer Tools: AWS CodePipeline, CodeBuild, CodeDeploy, CodeArtifact, and AWS X-Ray.

- 14.5 Management and Governance: AWS CloudFormation, AWS Config, AWS Control Tower, AWS Service Catalog, AWS Systems Manager, AWS License Manager, and AWS Launch Wizard.

- 14.6 Migration and Transfer: AWS Migration Hub, Application Discovery Service, Application Migration Service, and AWS DMS. Chapter 29 covers migration strategy.

- 14.7 Application Integration: Amazon SQS, Amazon SNS, Amazon EventBridge, AWS Step Functions, Amazon MQ, and AWS AppSync. Chapter 25 designs with them.

- 14.8 End User Computing and Business Applications: Amazon WorkSpaces, AppStream 2.0, Amazon Connect, and Amazon SES.

- 14.9 Buying and Support Resources: AWS Marketplace, AWS Partner Network, AWS Professional Services, AWS Skill Builder, and the AWS documentation and Knowledge Center.

### Chapter 15. Billing, Pricing, and Support

- 15.1 AWS Pricing Fundamentals: pay as you go, pay less when you reserve, pay less as you use more, and the services with no direct charge.

- 15.2 What Actually Drives a Bill: compute time, storage volume, data transfer out, and request counts.

- 15.3 The AWS Pricing Calculator: building an estimate, and reading the output.

- 15.4 Consolidated Billing with AWS Organizations: linked accounts, volume discount aggregation, and reserved capacity sharing. Chapter 8 owns the Organizations definition.

- 15.5 The Billing and Cost Management Console: Cost Explorer, AWS Budgets, Cost and Usage Reports, cost allocation tags, and Cost Anomaly Detection.

- 15.6 Cost Optimization Levers: the practical actions that reduce a bill, ordered by typical impact.

- 15.7 AWS Support Plans: the current Basic, Developer, Business, Enterprise On-Ramp, and Enterprise plans with their response commitments, and the transition to Business Support+, Enterprise Support, and Unified Operations as Developer, Business, and Enterprise On-Ramp are discontinued on January 1, 2027.

- 15.8 Getting Help: the Support Center, the Technical Account Manager role, and the concierge and guidance resources tied to each plan.

---
## Part III: AWS Certified Solutions Architect Associate (SAA-C03)

### Chapter 16. Cloud Architecting and the Architect Role

- 16.1 What Cloud Architecture Is: applying cloud characteristics to business and technical requirements.

- 16.2 The Cloud Architect Role: the plan, research, and build phases, and the responsibilities in each.

- 16.3 Roles Around the Architect: IT professional, IT leader, developer, and DevOps engineer, and how the work divides.

- 16.4 The SAA-C03 Exam at a Glance: domains, weightings, format, and duration, from the official exam guide.

- 16.5 The Running Case Study: the cafe business scenario that drives design decisions across Part III.

- 16.6 Applying the Well-Architected Framework: turning the six pillars from Chapter 6 into review questions for a real design.

- 16.7 Resource Placement Decisions: choosing Regions, Availability Zones, and edge locations for a stated requirement.

- 16.8 How to Read a Scenario Question: identifying the constraint that actually decides the answer.

### Chapter 17. Designing Secure Access

- 17.1 Least Privilege in Practice: narrowing a broad policy to a scoped one, step by step.

- 17.2 Policy Evaluation Logic: explicit deny, explicit allow, implicit deny, and the effect of permission boundaries and SCPs.

- 17.3 Roles for Workloads: EC2 instance profiles, Lambda execution roles, ECS task roles, and IAM roles for service accounts on EKS.

- 17.4 Cross-Account Access: AWS STS assume-role patterns, trust policies, external IDs, and AWS Resource Access Manager for resource sharing.

- 17.5 Workforce and Customer Identity: IAM Identity Center and SAML federation for staff, Amazon Cognito user pools and identity pools for applications.

- 17.6 Resource-Based Policies: S3 bucket policies, KMS key policies, and cross-account resource access.

- 17.7 Data Security Controls: KMS key types and rotation, envelope encryption, Secrets Manager versus Parameter Store, and certificate management with ACM.

- 17.8 Designing for Auditability: CloudTrail organization trails, AWS Config rules, and IAM Access Analyzer.

### Chapter 18. Designing the Storage Layer

- 18.1 Choosing a Storage Service: block, file, and object criteria applied to scenarios.

- 18.2 Selecting an S3 Storage Class: matching access pattern, retrieval time, and minimum duration to cost.

- 18.3 Lifecycle and Intelligent-Tiering Strategy: automating transitions and expiration, and when Intelligent-Tiering wins.

- 18.4 S3 Security Design: Block Public Access, bucket policies versus IAM policies, presigned URLs, encryption options, and Object Lock for compliance retention.

- 18.5 S3 Performance Design: request rate and prefixes, multipart upload, Transfer Acceleration, byte-range fetches, and S3 Select.

- 18.6 Replication Design: cross-Region and same-Region replication, requirements, and what replication does not cover.

- 18.7 Shared File Storage Design: EFS performance and throughput mode selection, and FSx choices for Windows and high-performance workloads.

- 18.8 Hybrid Storage Design: Storage Gateway modes, DataSync, and Snow Family selection for a stated bandwidth and timeline.

### Chapter 19. Designing the Compute Layer

- 19.1 Selecting an Instance Family: matching workload profile to general purpose, compute, memory, storage, and accelerated families, including Graviton.

- 19.2 Placement Groups and Tenancy: cluster, spread, and partition groups, plus dedicated instances and dedicated hosts.

- 19.3 Designing with Spot and Savings Plans: interruption handling, mixed instance policies, and commitment strategy.

- 19.4 AMI and Golden Image Strategy: building with EC2 Image Builder, versioning, and cross-account sharing.

- 19.5 Container Architecture Choices: ECS on EC2, ECS on Fargate, and EKS, with the trade-offs and the cost profile of each.

- 19.6 Serverless Compute Design: Lambda concurrency, cold starts, VPC-attached functions, and the cases where serverless is the wrong fit.

- 19.7 Right Sizing with Data: AWS Compute Optimizer and CloudWatch metrics as the evidence for a sizing decision.

### Chapter 20. Designing the Database Layer

- 20.1 Selecting a Database Service: a scenario-driven decision path across relational, key-value, in-memory, document, graph, and warehouse options.

- 20.2 Designing for RDS Availability: Multi-AZ instance deployments, Multi-AZ DB clusters, failover behavior, and the effect on the application.

- 20.3 Scaling Reads and Writes: read replicas, Aurora reader endpoints, Aurora Auto Scaling, and sharding considerations.

- 20.4 DynamoDB Data Modeling: partition key selection, hot partition avoidance, GSI versus LSI, single-table design, and capacity mode choice.

- 20.5 Caching the Data Tier: ElastiCache for Redis versus Memcached, DynamoDB Accelerator, and cache invalidation strategy.

- 20.6 Database Security: encryption at rest and in transit, IAM database authentication, and automated secret rotation.

- 20.7 Backup and Recovery for Databases: automated backups, manual snapshots, point-in-time recovery, and Aurora Backtrack.

### Chapter 21. Designing the Network Environment

- 21.1 VPC Sizing and Subnet Design: CIDR planning that leaves room for growth and avoids overlap.

- 21.2 The Three-Tier Subnet Layout: public, private, and isolated tiers, and the routing that separates them.

- 21.3 NAT Design: NAT gateway placement, per-AZ redundancy, and cost control.

- 21.4 VPC Endpoints: gateway versus interface endpoints, and the traffic they keep off the public internet.

- 21.5 Layered Network Security: security groups, network ACLs, AWS WAF, AWS Shield, and AWS Network Firewall, and which control belongs at which layer.

- 21.6 IPv6 and Dual-Stack Design: when to enable it, and what changes in routing and security groups.

- 21.7 Network Observability: VPC Flow Logs, Traffic Mirroring, and Reachability Analyzer.

### Chapter 22. Connecting Networks

- 22.1 VPC Peering: topology, quotas, and the non-transitive rule.

- 22.2 AWS Transit Gateway: hub-and-spoke design, route tables, and scaling beyond peering.

- 22.3 AWS Site-to-Site VPN: tunnel redundancy, routing options, and realistic throughput.

- 22.4 AWS Direct Connect: connection types, virtual interfaces, and the resilience models.

- 22.5 AWS PrivateLink: publishing and consuming services privately across accounts and VPCs.

- 22.6 Hybrid DNS: Route 53 Resolver inbound and outbound endpoints, and forwarding rules.

- 22.7 Choosing a Connectivity Option: a comparison table driven by bandwidth, latency, resilience, and cost.

### Chapter 23. Designing for Elasticity and High Availability

- 23.1 Multi-AZ and Multi-Region Patterns: what each protects against, and what each costs.

- 23.2 Scaling Policies in Depth: target tracking, step, simple, scheduled, and predictive scaling, with the selection rule.

- 23.3 Health Checks and Automatic Recovery: load balancer, Auto Scaling, and Route 53 health checks working together.

- 23.4 Stateless Application Design: session handling, shared storage, and why statelessness is what makes scaling work.

- 23.5 Route 53 Routing for Availability: failover, latency, weighted, geolocation, geoproximity, and multivalue answer routing.

- 23.6 Global Traffic Distribution: AWS Global Accelerator compared with CloudFront and Route 53 latency routing.

- 23.7 Observability for Architects: composite alarms, AWS X-Ray tracing, CloudWatch Logs Insights, and Synthetics canaries.

- 23.8 Service Quotas and Throttling: designing within limits, and requesting increases before they bite.

### Chapter 24. Caching and Content Delivery

- 24.1 Where Caching Belongs: edge, application, and database caching layers.

- 24.2 CloudFront Design: origins and origin groups, cache and origin request policies, TTL control, invalidations, and signed URLs and cookies.

- 24.3 Origin Protection: origin access control for S3, and shielding custom origins.

- 24.4 Edge Compute: CloudFront Functions versus Lambda@Edge, with the selection rule.

- 24.5 Application and Database Caching: ElastiCache patterns, lazy loading versus write-through, and DynamoDB Accelerator.

- 24.6 Caching Trade-Offs: staleness, invalidation complexity, and cost.

### Chapter 25. Decoupled and Event-Driven Architectures

- 25.1 Why Decouple: failure isolation, independent scaling, and load buffering.

- 25.2 Amazon SQS: standard versus FIFO, visibility timeout, long polling, dead-letter queues, and message retention.

- 25.3 Amazon SNS: topics, subscriptions, fan-out, filter policies, and FIFO topics.

- 25.4 Amazon EventBridge: event buses, rules, schema registry, scheduled events, and partner event sources.

- 25.5 AWS Step Functions: standard versus express workflows, and orchestration versus choreography.

- 25.6 Amazon MQ and Amazon Kinesis: when a managed broker or a stream beats a queue.

- 25.7 Choosing a Decoupling Service: a comparison table driven by ordering, retention, fan-out, and replay requirements.

### Chapter 26. Serverless Architectures and Microservices

- 26.1 A Reference Serverless Architecture: API Gateway, Lambda, DynamoDB, and S3 working together.

- 26.2 Amazon API Gateway: REST, HTTP, and WebSocket APIs, authorizers, throttling, usage plans, and caching.

- 26.3 Lambda in Production: versions, aliases, provisioned concurrency, layers, and event source mappings.

- 26.4 Microservice Boundaries: sizing services, and the data ownership rule.

- 26.5 Serverless Data Access: connection management, RDS Proxy, and DynamoDB access patterns.

- 26.6 Serverless Trade-Offs: cost curves, latency, vendor coupling, and testing difficulty.

### Chapter 27. Automating the Architecture

- 27.1 Infrastructure as Code Principles: declarative definition, idempotency, and version control.

- 27.2 AWS CloudFormation: templates, stacks, parameters, outputs and exports, change sets, drift detection, and StackSets. This chapter owns the design view; Chapter 35 covers the CLI operations.

- 27.3 CDK, SAM, and Terraform: how each relates to CloudFormation, and where each is used in practice. Chapter 37 teaches Terraform hands-on.

- 27.4 Deployment Strategies: in-place, rolling, blue/green, and canary, with the rollback story for each.

- 27.5 CI/CD on AWS: CodePipeline, CodeBuild, and CodeDeploy, and where approvals and tests belong.

- 27.6 Operational Automation: Systems Manager Automation runbooks, EventBridge-triggered remediation, and AWS Config auto-remediation.

### Chapter 28. Data Engineering and Analytics Patterns

- 28.1 Batch versus Streaming: choosing the processing model from the latency requirement.

- 28.2 Ingestion: Kinesis Data Streams, Amazon Data Firehose, and Amazon MSK.

- 28.3 The S3 Data Lake: zone layout, partitioning, file formats, and AWS Lake Formation permissions.

- 28.4 Transformation and Cataloging: AWS Glue crawlers, the Data Catalog, and Glue ETL jobs.

- 28.5 Query and Analysis: Amazon Athena, Amazon Redshift and Redshift Spectrum, and Amazon EMR.

- 28.6 Search and Visualization: Amazon OpenSearch Service and Amazon QuickSight.

- 28.7 A Worked Pipeline: ingest, store, catalog, transform, query, and visualize, end to end.

### Chapter 29. Migration and Disaster Recovery

- 29.1 Migration Strategies: the seven Rs, and how to choose one per application.

- 29.2 Discovery and Planning: Migration Hub, Application Discovery Service, and building a migration wave plan.

- 29.3 Migration Execution: Application Migration Service for servers, AWS DMS and the Schema Conversion Tool for databases, and DataSync and Snow Family for bulk data.

- 29.4 RTO and RPO: defining the targets before choosing a recovery strategy.

- 29.5 The Four DR Strategies: backup and restore, pilot light, warm standby, and multi-site active/active, with cost and recovery time for each.

- 29.6 Backup Design: AWS Backup plans, snapshot lifecycle, and cross-Region and cross-account copies.

- 29.7 Replication Choices for DR: S3 replication, RDS and Aurora cross-Region options, and DynamoDB global tables.

- 29.8 Testing and Runbooks: proving the plan works before you need it.

### Chapter 30. Cost-Optimized Architectures and Exam Readiness

- 30.1 Cost-Aware Design Decisions: the architectural choices with the largest bill impact.

- 30.2 Compute Cost Optimization: right sizing, Savings Plans, Spot, Graviton, and scheduled shutdowns, building on Chapter 10.

- 30.3 Storage and Data Transfer Cost Optimization: class selection, lifecycle rules, and the data transfer charges architects most often miss.

- 30.4 Managed Services as Cost Optimization: where paying for a managed service is cheaper than running your own.

- 30.5 Cost Governance at Scale: Organizations, budgets, cost allocation tags, and Cost Anomaly Detection applied across accounts.

- 30.6 Exam Strategy: reading scenario questions, eliminating distractors, and pacing.

- 30.7 Domain-by-Domain Review Checklist: a condensed revision list mapped to the four SAA-C03 domains.

- 30.8 Full-Length Practice Set: 20 scenario questions with answers and explanations, an exception to the usual 3 to 5 chapter questions.

---
## Part IV: Entry Level AWS Cloud Engineer Job Skills

### Chapter 31. The Entry Level Cloud Engineer Role

- 31.1 What the Job Actually Involves: the daily and weekly tasks behind the job description.

- 31.2 Choosing an Access Method: console, CLI, SDK, CloudShell, or infrastructure as code, with a comparison table for development, test, and production contexts.

- 31.3 The Credential Resolution Model: profiles, environment variables, instance and container roles, and the precedence order that applies to every tool in this part.

- 31.4 Working Conventions: naming, tagging, Region discipline, and the cleanup habit used in all remaining chapters.

- 31.5 Linux and Networking Skills You Are Assumed to Have: the shell, SSH, permissions, systemd, DNS, and TCP basics, with a self-check list.

### Chapter 32. AWS CLI v2

- 32.1 Installing the CLI: Linux, macOS, and Windows procedures, each with verification.

- 32.2 Configuration: interactive setup, the credentials and config files, and named profiles.

- 32.3 Environment Variables and Precedence: overriding a profile safely.

- 32.4 MFA and Temporary Credentials: get-session-token versus assume-role, and when each applies.

- 32.5 Output, Filtering, and Querying: JMESPath queries, output formats, pagination control, and the pager.

- 32.6 Troubleshooting: the common failures, their causes, and the fix for each.

### Chapter 33. CLI Operations: Compute, Storage, and Identity

- 33.1 Key Pairs: create, secure, list, and delete, on Linux and on PowerShell.

- 33.2 Security Groups: create, authorize ingress and egress, inspect, revoke, and delete.

- 33.3 EC2 Instances: launch, describe, tag, stop, start, terminate, and query Regions and Availability Zones.

- 33.4 EBS and AMIs: create and attach volumes, detach, snapshot, create images, and deregister.

- 33.5 Elastic IP Addresses: allocate, associate, disassociate, and release.

- 33.6 Amazon S3: bucket operations, object operations, sync, storage class on sync, encryption, and versioning.

- 33.7 IAM: users, groups, policies and versions, roles, access keys, permission boundaries, policy simulation, and credential reports.

- 33.8 Cleanup Sequences: deleting resources in dependency order without leaving orphans.

### Chapter 34. CLI Operations: Application and Data Services

- 34.1 Amazon RDS: create, wait, retrieve endpoints, snapshot and restore, modify and scale, read replicas, Multi-AZ, and parameter groups.

- 34.2 AWS Lambda: execution roles, packaging, create and update, versions and aliases, invocation patterns, concurrency, layers, and event source mappings.

- 34.3 Amazon CloudWatch and Logs: metric queries, custom metrics, alarms, log groups and retention, filtering, Logs Insights queries, subscription filters, and dashboards.

- 34.4 Amazon DynamoDB: table operations, indexes, capacity modes, item and batch operations, queries and scans, transactions, PartiQL, and backups.

- 34.5 Amazon SNS: topics, subscriptions, publishing, message attributes and filter policies, encryption, and delivery status logging.

- 34.6 Amazon SQS: standard and FIFO queues, attributes, dead-letter queues, message operations, batching, and long polling.

- 34.7 Putting It Together: a scripted SNS to SQS to Lambda pipeline built entirely from the CLI.

### Chapter 35. CLI Operations: Automation, Containers, and CloudShell

- 35.1 AWS CloudFormation: validate, deploy, change sets, stack queries and events, outputs and exports, waiters, drift detection, stack policies, nested stacks, packaging, and StackSets.

- 35.2 AWS Systems Manager: Session Manager, port forwarding, Run Command, documents, Automation, Patch Manager, inventory and compliance, maintenance windows, and Parameter Store.

- 35.3 Amazon ECR: repositories, authentication, build and tag and push, image scanning, lifecycle policies, replication, and multi-architecture images.

- 35.4 Amazon EKS: clusters, kubeconfig and authentication, managed node groups, Fargate profiles, add-ons, version upgrades, IRSA, and cluster networking.

- 35.5 AWS CloudShell: session lifecycle, persistent storage, package management, environment customization, and multi-Region scripting patterns.

- 35.6 Docker Fundamentals for Cloud Engineers: images, layers, registries, and the build-to-ECR workflow.

### Chapter 36. AWS SDKs

- 36.1 SDK Fundamentals: clients, credential resolution, Region selection, and error shapes, common to every language.

- 36.2 Python with Boto3: install, virtual environments, clients versus resources, waiters, pagination, and worked EC2 and S3 scripts.

- 36.3 Node.js with AWS SDK v3: project setup, modular clients, pagination, waiters, and middleware.

- 36.4 Java with SDK v2: dependency management, client construction, pagination, and waiters.

- 36.5 .NET SDK: project setup, dependencies, async patterns, and pagination.

- 36.6 Cross-Cutting Concerns: STS and temporary credentials, retry modes and backoff, timeouts, logging, and local testing.

- 36.7 SDK Security Checklist: what never goes in source control, and what to use instead.

### Chapter 37. Infrastructure as Code with Terraform

- 37.1 Terraform and AWS: how it compares to CloudFormation, and where it fits in a junior engineer's work.

- 37.2 Installation and Provider Configuration: setting up the AWS provider and supplying credentials safely.

- 37.3 A First Project: a data source for the latest AMI, a security group, and an EC2 instance.

- 37.4 The Core Workflow: init, validate, plan, apply, and destroy.

- 37.5 Variables and Outputs: declaring, supplying by file, environment variable and flag, and consuming outputs.

- 37.6 State: what state holds, why it matters, and remote state on S3 with DynamoDB locking.

- 37.7 Registry Modules: using the VPC module, and placing compute inside it.

- 37.8 Practices and Troubleshooting: version pinning, secret handling, log levels, and the common errors.

### Chapter 38. Working Practices and Capstone

- 38.1 Version Control for Infrastructure: Git workflow, branching, pull requests, and what belongs in .gitignore.

- 38.2 Documentation and Runbooks: writing the handover document your team will actually use.

- 38.3 Troubleshooting Method: a repeatable sequence for connectivity, permissions, and capacity failures, with the AWS tool to reach for at each stage.

- 38.4 On-Call Basics: reading an alarm, triaging by blast radius, and escalating well.

- 38.5 Capstone Project: build a highly available web application from code, comprising a VPC across two Availability Zones, an Application Load Balancer, an Auto Scaling group, an RDS Multi-AZ backend, remote Terraform state, CloudWatch alarms, and a validation checklist, followed by full teardown.

- 38.6 Portfolio and Interview Readiness: turning the labs and capstone into evidence, and the questions entry-level candidates are actually asked.