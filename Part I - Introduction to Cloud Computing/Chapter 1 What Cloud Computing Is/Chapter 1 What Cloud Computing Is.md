# Chapter 1: What Cloud Computing Is

---
## 1.1 Definition and Characteristics

Cloud computing is the on-demand delivery of compute power, database, storage, applications, and other IT resources over the internet, with pay-as-you-go pricing.

Three ideas do the real work in that sentence:

- **On-demand.** You request a resource when you need it and it is available in minutes. Nobody signs a purchase order and nobody waits for a delivery.

- **Over the internet.** The hardware sits in a provider's data center. You reach it through an API, a web console, or a command line, not through a rack in your building.

- **Pay-as-you-go.** You are billed for what you consume, by the second, gigabyte, or request, depending on the service. Stop using a resource and the charge stops.

**A widely used framing describes cloud computing through five characteristics:** 

- On-demand self-service
- Broad network access
- Resource pooling
- Rapid elasticity
- Measured service. 

>[This five-characteristic model comes from NIST Special Publication 800-145, not from AWS. It is useful as a mental model, but AWS exams use AWS's own wording, covered in section 1.5.]

Two terms appear constantly from here on, and they are not the same thing:

- **Scalability** is the ability to increase or decrease capacity to match demand.

- **Elasticity** is doing that automatically, in response to demand, without a person deciding.

Both are defined in full in Chapter 5, alongside fault tolerance and high availability.

---
## 1.2 Infrastructure as Software

The shift that matters is not that servers moved somewhere else. It is that infrastructure stopped being a physical object and became something you describe in code.

- A server becomes an API call rather than a purchase, a delivery, and a rack installation.

- A network becomes a configuration file rather than cabling and switch ports.

- A firewall rule becomes a versioned text file that can be reviewed, tested, and rolled back.

- An entire environment can be destroyed and rebuilt identically, on demand, because its definition lives in a repository.

The practical consequences follow directly:

- **Speed.** Provisioning drops from weeks to minutes.

- **Repeatability.** The same definition produces the same environment every time, so development, test, and production stop drifting apart.

- **Reversibility.** A change that turns out badly can be rolled back, because the previous definition still exists.

- **Low cost of experiments.** Trying an idea costs an hour of instance time rather than a hardware budget, so more ideas get tried.

>This is the foundation for infrastructure as code, which appears as AWS CloudFormation in Chapter 27 and as Terraform in Chapter 37.

---
## 1.3 Traditional Computing Model vs Cloud Computing Model

| Aspect | Traditional (on-premises) | Cloud |
| --- | --- | --- |
| Infrastructure | Physical hardware needing space, power, cooling, physical security, and staff | Virtualized, software defined resources |
| Procurement | Purchase cycles measured in weeks or months | Resources available in minutes |
| Capacity planning | Peak demand must be estimated years ahead and bought upfront | Capacity adjusts to actual demand |
| Maintenance | Racking, patching, hardware replacement, and data center operations are yours | The provider handles the undifferentiated heavy lifting |
| Cost model | Large upfront capital cost plus ongoing operating cost | Operating cost only, tied to consumption |
| Failure of a guess | Overprovisioning wastes money; underprovisioning loses customers | Scale up or down and pay accordingly |
| Global reach | A second region means a second data center project | Deploy to another AWS Region from the console |

The row that decides most architecture arguments is capacity planning. In a traditional model you commit to a capacity number before you have any real usage data, and you are wrong in one of two expensive directions. Cloud removes the need to make that commitment at all.

>The financial side of this comparison, including capital versus operating expenditure and total cost of ownership, is covered in Chapter 3.

---
## 1.4 Similarities Between AWS and Traditional IT

Almost every on-premises component has a cloud counterpart. Mapping the familiar thing to the AWS thing is the fastest way in for anyone with existing infrastructure experience.

| Function | On-premises | AWS equivalent |
| --- | --- | --- |
| Compute | Physical or virtual servers | Amazon EC2 instances, launched from AMIs |
| Block storage | Direct attached storage, SAN | Amazon EBS volumes |
| File storage | NAS, file servers | Amazon EFS, Amazon FSx |
| Object storage | Local archive, tape libraries | Amazon S3, including the S3 Glacier storage classes |
| Relational database | Self managed database server | Amazon RDS, Amazon Aurora |
| Network isolation | VLANs, physical segmentation | Amazon VPC and its subnets |
| Routing and DNS | Routers, internal DNS servers | VPC route tables, Amazon Route 53 |
| Load balancing | Hardware load balancer appliance | Elastic Load Balancing |
| Firewalling | Firewall appliances, access control lists | Security groups, network ACLs, AWS Network Firewall |
| Identity | Active Directory, local accounts | AWS IAM, IAM Identity Center, AWS Directory Service |
| Monitoring | Nagios, Zabbix, syslog servers | Amazon CloudWatch, AWS CloudTrail |


>Two cautions about this table:

>The mapping is functional, not literal. A security group is not a firewall appliance in a different form. It is stateful, attaches to a network interface rather than a network boundary, and cannot express a deny rule at all. The differences are covered in Chapter 9.

>Managed services shift responsibility as well as location. Moving a database from a server you patch to Amazon RDS changes who applies the patch. That reallocation is the shared responsibility model, covered in Chapter 8.

---
## 1.5 The Six Advantages of Cloud Computing

AWS publishes six advantages of cloud computing in the Overview of Amazon Web Services whitepaper. They are tested directly on the Cloud Practitioner exam, and the wording is worth knowing.

1. **Trade fixed expense for variable expense.** 

Rather than investing in data centers and servers before you know how you will use them, pay only when you consume resources, and only for what you consume.

2. **Benefit from massive economies of scale.** 

Usage from hundreds of thousands of customers is aggregated, so a provider achieves a lower variable cost than any single organization can reach alone, and that shows up in pay-as-you-go prices.

3. **Stop guessing capacity.** 

Capacity decisions no longer have to be made before deployment, so you avoid both idle overprovisioned resources and the ceiling of underprovisioned ones.

4. **Increase speed and agility.** 

New resources are a few minutes away instead of weeks, which lowers the cost of experimenting and therefore raises how often you experiment.

5. **Stop spending money running and maintaining data centers.** 

Racking, stacking, and powering servers is effort that does not differentiate your business. Redirect it to customers and applications.

6. **Go global in minutes.** 

Deploy an application into multiple AWS Regions to place it near users, without building anything physical.

> Note: Older study material, including some AWS course content, states the first advantage as "trade capital expense for variable expense." The current whitepaper says "fixed expense." Both refer to the same idea, and an exam question may use either phrasing.

---
## 1.6 Categories of Cloud Services

These ten categories are the organizing spine of this course. Each one gets a full chapter or section later, so the goal here is recognition, not depth: given a problem, know which shelf to reach for.

### 1.6.1 Compute

Processing power to run application code.

- **Amazon EC2:** virtual machines with full operating system control.
- **Amazon ECS and Amazon EKS:** container orchestration, on EC2 or on AWS Fargate.
- **AWS Lambda:** run a function in response to an event, with no server to manage.
- **AWS Elastic Beanstalk:** upload application code and let AWS provision and manage what it needs to run.

>Covered in Chapter 10.

### 1.6.2 Storage

Somewhere to put data and get it back.

- **Amazon S3:** object storage for files, backups, static assets, and data lakes.
- **Amazon EBS:** block storage volumes attached to EC2 instances.
- **Amazon EFS:** shared file storage that many instances can mount at once.
- **S3 Glacier storage classes:** low-cost archival tiers within S3 for data that is rarely retrieved.

>Covered in Chapter 11.

### 1.6.3 Networking and Content Delivery

Connectivity between resources, and between resources and users.

- **Amazon VPC:** a logically isolated network in which your resources run.
- **Elastic Load Balancing:** distributes incoming traffic across multiple targets.
- **Amazon Route 53:** DNS and health-checked traffic routing.
- **Amazon CloudFront:** content delivery from edge locations close to users.

>Covered in Chapter 9.

### 1.6.4 Database

Managed data stores, so you are not patching database servers.

- **Amazon RDS:** managed relational databases including MySQL, PostgreSQL, MariaDB, Oracle, and SQL Server.
- **Amazon Aurora:** a MySQL and PostgreSQL compatible engine built for the cloud.
- **Amazon DynamoDB:** serverless key-value and document database.
- **Amazon Redshift:** data warehouse for analytics over large datasets.

>Covered in Chapter 12.

### 1.6.5 Development, Messaging, and Deployment

Tools for building, connecting, and shipping applications.

- **AWS CodePipeline, CodeBuild, and CodeDeploy:** build and release automation.
- **Amazon SQS:** managed message queues that decouple producers from consumers.
- **Amazon SNS:** publish and subscribe messaging and notifications.
- **AWS CloudFormation:** define infrastructure in a template and deploy it as a stack.

>Covered in Chapters 25 and 27.

### 1.6.6 Migration and Transfer

Moving workloads and data into AWS.

- **AWS Database Migration Service:** move databases with minimal downtime, including between different engines.
- **AWS Application Migration Service:** lift and shift servers into AWS.
- **AWS DataSync:** automated online transfer between on-premises storage and AWS.
- **AWS Transfer Family:** managed SFTP, FTPS, and FTP endpoints in front of S3 and EFS.

>A note on the Snow Family, which appears in most older material: AWS retired Snowmobile, discontinued Snowcone, and as of November 7, 2025 makes Snowball Edge available to existing customers only. New workloads should plan around DataSync, AWS Data Transfer Terminal, or partner solutions for bulk transfer, and AWS Outposts for edge compute. Migration strategy is covered in Chapter 29.

### 1.6.7 AI and Machine Learning

Trained models and model-building platforms, consumed as services.

- **Amazon SageMaker:** build, train, and deploy custom machine learning models.
- **Amazon Bedrock:** access foundation models through an API to build generative AI applications.
- **Amazon Rekognition:** image and video analysis.
- **Amazon Comprehend:** natural language processing over text.

>Covered in Chapter 14.

### 1.6.8 Auditing, Monitoring, and Logging

Knowing what your systems are doing, and what people did to them.

- **Amazon CloudWatch:** metrics, logs, dashboards, and alarms.
- **AWS CloudTrail:** a record of API calls made in the account, for audit and investigation.
- **AWS Config:** tracks resource configuration over time and evaluates it against rules.
- **AWS X-Ray:** distributed tracing through a request's path across services.

>Covered in Chapters 13 and 23.

### 1.6.9 Security, Compliance, and Governance

Controlling access, protecting data, and demonstrating both.

- **AWS IAM:** users, groups, roles, and policies that decide who can do what.
- **AWS KMS:** creation and control of encryption keys.
- **Amazon GuardDuty:** continuous threat detection from account and network activity.
- **AWS WAF:** filtering of malicious web requests before they reach an application.

>Covered in Chapters 8 and 17.

### 1.6.10 Pricing, Billing, and Support

Seeing and controlling what you spend, and getting help.

- **AWS Budgets:** alerts when cost or usage crosses a threshold you set.
- **AWS Cost Explorer:** analysis of spending trends and forecasts.
- **AWS Trusted Advisor:** recommendations across cost, performance, security, fault tolerance, service limits, and operational excellence.
- **AWS Support plans:** Basic, Developer, Business, Enterprise On-Ramp, and Enterprise.

>Covered in Chapter 15.

---
## 1.7 End of Chapter Questions

**Q1.** A retailer buys enough servers to handle its busiest shopping day of the year. For the remaining eleven months, most of that hardware sits idle. Which advantage of cloud computing addresses this problem directly?

- A. Increase speed and agility
- B. Stop guessing capacity
- C. Go global in minutes
- D. Benefit from massive economies of scale

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* The waste comes from a capacity commitment made before demand was known, which is exactly the guess that cloud elasticity removes.

**Q2.** Which statement best describes cloud computing?

- A. Renting physical servers in a colocation facility that you administer remotely
- B. On-demand delivery of IT resources over the internet with pay-as-you-go pricing
- C. Running virtualization software on hardware your company owns
- D. Outsourcing an application to a managed service provider under a fixed annual contract

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* The other options all keep either the hardware commitment or the fixed contract, so none of them delivers on-demand, consumption-based resourcing.

**Q3.** A team currently uses hardware load balancers and Active Directory on-premises. Which pair of AWS services corresponds to those functions?

- A. Amazon Route 53 and Amazon EC2
- B. Amazon CloudFront and AWS KMS
- C. Elastic Load Balancing and AWS IAM
- D. Amazon VPC and Amazon S3

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* Elastic Load Balancing replaces the load balancer appliance and IAM provides the identity and access control layer.

**Q4.** An architect is asked why moving to AWS lets the company test new product ideas more often than it did on-premises. Which characteristic of cloud infrastructure is the strongest explanation?

- A. Resources are defined in software and can be provisioned and destroyed in minutes, so a failed experiment costs very little
- B. AWS Regions are physically isolated from one another
- C. AWS provides more service categories than the company previously ran
- D. Data stored in Amazon S3 is durable across multiple facilities

**Answer: A.** *Target exam: AWS Certified Solutions Architect - Associate.* Experiment frequency is driven by the cost and time of a failed attempt, and treating infrastructure as software drives both close to zero.

---
### Resources
 
- [What is cloud computing?](https://aws.amazon.com/what-is-cloud-computing/)
- [Overview of Amazon Web Services (whitepaper)](https://docs.aws.amazon.com/whitepapers/latest/aws-overview/introduction.html)
- [Six advantages of cloud computing](https://docs.aws.amazon.com/whitepapers/latest/aws-overview/six-advantages-of-cloud-computing.html)
- [AWS Cloud Products](https://aws.amazon.com/products/)

--