# Chapter 4: Introduction to Amazon Web Services

---

Part I dealt with cloud computing as a concept, independent of any provider. Part II narrows to AWS and to the Cloud Practitioner certification. This chapter sets up the vocabulary, the exam, and the ways you will actually touch the platform for the rest of the course.

---
## 4.1 What Web Services Are

- Software components that are accessible over a network and callable by other software rather than by a person at a screen.
- Interoperable across platforms and languages, so a Python client can call a service implemented in Java without knowing or caring.
- Exchange data in standardized formats, usually JSON, sometimes XML.
- Exposed through a well-defined API, which is the contract stating what operations exist, what each one accepts, and what it returns.

This matters because it describes what AWS actually is underneath. Every AWS service is an API. The console, the CLI, the SDKs, and CloudFormation are all clients that make the same underlying API calls on your behalf.

The practical consequence appears immediately: anything you can do by clicking in the console, you can do from a script. That is what makes automation possible, and it is why Part IV spends four chapters on programmatic access.

---
## 4.2 What AWS Is

- A cloud platform providing on-demand access to compute, storage, networking, database, analytics, machine learning, security, and management services.
- Globally distributed, with infrastructure organized into Regions and Availability Zones, covered in Chapter 5.
- Priced on consumption, with no minimum commitment for most services and no charge when a resource is not running.
- Composed of modular services designed to be combined. Very few workloads use one service; almost all combine several.

**The building block idea**

A typical web application on AWS is assembled rather than built:

- Amazon Route 53 resolves the domain name.
- Amazon CloudFront serves cached content from an edge location near the user.
- An Application Load Balancer distributes requests.
- Amazon EC2 instances in an Auto Scaling group run the application.
- Amazon RDS holds the relational data.
- Amazon S3 stores uploads and static assets.
- AWS IAM controls what each component may do.
- Amazon CloudWatch records what is happening.

None of those services was written for that specific application. Each one solves a general problem, and the architecture is the choice of which ones to combine and how to wire them together. That framing is the whole of Part III.

---
## 4.3 The CLF-C02 Exam at a Glance

The AWS Certified Cloud Practitioner exam validates overall understanding of the AWS Cloud independent of any specific job role. The target candidate has up to six months of exposure to AWS.

**Domains and weightings**

| Domain | Content | Weight |
| --- | --- | --- |
| 1 | Cloud Concepts | 24% |
| 2 | Security and Compliance | 30% |
| 3 | Cloud Technology and Services | 34% |
| 4 | Billing, Pricing, and Support | 12% |

**Format and scoring**

- 50 scored questions plus 15 unscored questions that do not affect your result and are not identified during the exam.
- Multiple choice, with one correct answer from four options, and multiple response, with two or more correct answers from five or more options.
- Results are reported as a scaled score from 100 to 1,000. The minimum passing score is 700.
- Scoring is compensatory, meaning you do not need to pass each domain individually, only the exam overall.
- [The exam duration is widely reported as 90 minutes, and the exam fee varies by region. Neither is stated in the exam guide itself, so confirm both on the AWS certification page when booking.]

**What the exam asks you to do**

- Explain the value of the AWS Cloud.
- Understand and explain the shared responsibility model.
- Understand the AWS Well-Architected Framework.
- Understand security and compliance best practices.
- Understand AWS Cloud costs, economics, and billing practices.
- Describe and position the core compute, network, database, and storage services.
- Identify AWS services for common use cases.

Note the phrasing throughout that list: explain, describe, identify. This is a recognition exam, not a configuration exam. You are expected to know which service solves which problem, not how to tune it.

---
## 4.4 Ways to Interact with AWS

Every method below results in the same API calls. They differ in convenience, in how well they scale to repeated work, and in how safely they behave under pressure.

| Method | Ease of use | Automation | Suits learners | Suits production |
| --- | --- | --- | --- | --- |
| AWS Management Console | High | Low | Yes | Only with IAM Identity Center sign-in |
| AWS CLI v2 | Medium | High | Yes | Yes |
| AWS SDKs, for example Boto3 for Python | Medium | High | Yes | Yes |
| AWS CloudShell | High | Medium | Yes | Yes |
| Infrastructure as code, CloudFormation or Terraform | Medium | High | With guidance | Yes, and preferred |
| IAM Identity Center for sign-in | Medium | High | Not usually needed | Yes |
| AWS Systems Manager Session Manager | Medium | Medium | With guidance | Yes |
| Direct API calls signed with Signature Version 4 | Low | High | Rarely | Yes, usually via an SDK |

**AWS Management Console**

- A browser-based graphical interface covering effectively every service.
- Best for learning, for exploring an unfamiliar service, and for reading state such as logs, metrics, and configuration.
- Poor for anything performed more than a few times, because a sequence of clicks cannot be reviewed, versioned, or repeated reliably.
- Covered hands-on in Chapter 7.

**AWS CLI**

- Command line access to the same APIs, suitable for scripting and for combining with normal shell tooling.
- The natural choice for one-off administrative tasks and for automation that does not justify a full application.
- Covered in Chapters 32 to 35.

**AWS SDKs**

- Libraries for Python, JavaScript, Java, .NET, Go, and others, used when AWS calls belong inside application code.
- Covered in Chapter 36.

**AWS CloudShell**

- A browser-based shell, launched from the console, preauthenticated as your signed-in identity, with the CLI and common tools already installed.
- Useful when you need a command line without configuring credentials on a laptop.
- Covered in Chapter 35.

**Infrastructure as code**

- Resources are defined in a template or configuration file and created from it, so the environment is versioned, reviewable, and reproducible.
- The correct default for anything that must exist tomorrow as well as today.
- Covered in Chapter 27 for CloudFormation and Chapter 37 for Terraform.

A rule that holds up well in practice: explore in the console, operate from the CLI, and build with infrastructure as code.

---
## 4.5 The AWS Service Layers

AWS services are often described as four stacked layers. The model is a simplification, but it is a useful one for orientation, because it explains which services depend on which.

**Infrastructure layer**

- The physical foundation: Regions, Availability Zones, and edge locations.
- You choose where things run, but you do not manage anything at this layer.

**Foundation services layer**

- Compute: Amazon EC2, AWS Lambda.
- Networking: Amazon VPC, Amazon Route 53.
- Storage: Amazon S3 for objects, Amazon EBS for block, Amazon EFS for file.

These are the primitives. Higher layers are built from them, and so are most customer architectures.

**Platform services layer**

- Databases: Amazon RDS, Amazon DynamoDB.
- Caching: Amazon ElastiCache.
- Analytics: Amazon Kinesis, Amazon Redshift, AWS Glue.
- Application integration: Amazon SQS, Amazon SNS, AWS Step Functions.
- Containers and deployment: Amazon ECS, Amazon EKS, AWS CodePipeline, AWS CloudFormation.
- Monitoring: Amazon CloudWatch.
- Identity for applications: Amazon Cognito.

These remove operational work. Amazon RDS still runs on compute, storage, and networking, but AWS operates that plumbing rather than you.

**Applications layer**

- Finished applications delivered as a service, such as Amazon WorkSpaces for virtual desktops and Amazon Connect for contact centers.

Two things to keep straight:

- The layers describe dependency and abstraction, not importance. Foundation services are not simpler in use; they simply expose more control and require more of you.
- Moving up a layer trades control for reduced operational effort, which is the same trade described by IaaS, PaaS, and SaaS in Chapter 2.

---
## 4.6 How to Study for This Exam

The four exam domains do not map neatly onto a sensible teaching order, so this course teaches in a learnable sequence and this table shows where each domain is covered.

| Exam domain | Weight | Where it is covered |
| --- | --- | --- |
| Cloud Concepts | 24% | Chapters 1 to 3, and Chapters 4 to 6 |
| Security and Compliance | 30% | Chapter 8, with account setup in Chapter 7 |
| Cloud Technology and Services | 34% | Chapters 9 to 14 |
| Billing, Pricing, and Support | 12% | Chapter 15, building on Chapter 3 |

Practical advice for this exam specifically:

- **Weight your revision by domain.** Security and Compliance plus Cloud Technology and Services together account for 64% of the scored content. Cloud Concepts and Billing are worth less than they feel while studying.
- **Learn the shared responsibility model properly.** It is the most frequently tested single concept on the exam, and it is covered in section 8.1.
- **Know what each service is for, not how it is configured.** A question will describe a problem and ask which service addresses it. Depth beyond that is wasted effort at this level.
- **Do the labs anyway.** They are not required for the exam, but service names stop blurring together once you have launched, connected to, and deleted the thing.
- **Read the official exam guide before booking.** It lists the in-scope services explicitly, and it is the only authoritative source for what may appear.

---
## 4.7 End of Chapter Questions

**Q1.** Which two statements about interacting with AWS are correct? Choose two.

- A. The AWS Management Console, the AWS CLI, and the AWS SDKs all ultimately call the same service APIs
- B. Some AWS services can only be configured through the Management Console and have no API
- C. The AWS CLI is better suited than the console to tasks that must be repeated reliably
- D. AWS SDKs are available only for Python and Java
- E. Infrastructure as code requires an AWS Support plan

**Answer: A and C.** *Target exam: AWS Certified Cloud Practitioner.* Every access method is a client of the same API, and the CLI is scriptable in a way that a sequence of console clicks is not.

**Q2.** Which domain carries the largest weighting on the CLF-C02 exam?

- A. Cloud Concepts
- B. Security and Compliance
- C. Cloud Technology and Services
- D. Billing, Pricing, and Support

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* Cloud Technology and Services accounts for 34%, ahead of Security and Compliance at 30%.

**Q3.** A team needs a command line to run a few AWS CLI commands, but company policy forbids storing long-lived access keys on laptops. Which option meets this requirement with the least setup?

- A. Install the AWS CLI locally and configure an access key
- B. Use AWS CloudShell from the browser, which runs preauthenticated as the signed-in identity
- C. Write an application using an AWS SDK
- D. Raise a support case to request temporary credentials

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* CloudShell provides a preconfigured shell in the browser using the console session's identity, so no credentials are stored on the device.

**Q4.** In the AWS service layer model, which layer contains Amazon EC2, Amazon VPC, and Amazon S3?

- A. Infrastructure layer
- B. Foundation services layer
- C. Platform services layer
- D. Applications layer

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* Compute, networking, and storage primitives sit in the foundation services layer, above the physical infrastructure and below the managed platform services.

**Q5.** An engineer needs to create the same three-tier environment in development, test, and production, and to guarantee that all three remain identical over time. Which approach is most appropriate?

- A. Document the console steps and have each team follow them
- B. Build the first environment in the console and copy resources manually
- C. Define the environment as infrastructure as code and deploy the same template to each account
- D. Use the AWS CLI interactively in each environment

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Only a versioned definition deployed from a single source guarantees the three environments match and stay matched as changes are made.
