# Chapter 15: Billing, Pricing, and Support

---

Billing, Pricing, and Support is 12% of the CLF-C02 exam, the smallest of the four domains but a straightforward one to score on. Chapter 3 covered the economics of cloud in general terms. This chapter covers the AWS-specific mechanics.

---

## 15.1 AWS Pricing Fundamentals

AWS pricing rests on four principles, which appear on the exam close to verbatim.

**Pay for what you use.** Consumption-based pricing with no large upfront capital expense. Turn a resource off and billing for it stops.

**Pay less when you reserve.** Committing to one or three years through Reserved Instances or Savings Plans earns a substantial discount against On-Demand rates. Reserved Instances offer three payment structures:

| Payment option | Effective discount |
| --- | --- |
| All Upfront | Largest |
| Partial Upfront | Middle |
| No Upfront | Smallest, paid monthly across the term |

Savings Plans deliver comparable savings with more flexibility, covering EC2, AWS Lambda, and AWS Fargate rather than a specific instance type, and are generally the better choice for new commitments.

**Pay less by using more.** Tiered pricing on services such as Amazon S3, EBS, and EFS reduces the per-GB rate as usage grows.

**Pay even less as AWS grows.** AWS states it has reduced prices more than 100 times since launching in 2006, passing economies of scale back to customers.

Organizations with high volume or specialized requirements may also negotiate private pricing agreements directly with AWS.

### 15.1.1 The Free Tier

The AWS Free Tier was restructured on July 15, 2025, and the structure now depends on when your account was created. Section 7.2 covers both models in full, including the Free and Paid account plans, the credit allowances, and the six-month expiry that applies to new Free plan accounts. It is not repeated here.

### 15.1.2 Services with No Direct Charge

Some services carry no charge for the service itself, though you pay for the resources they provision:

- Amazon VPC
- AWS Elastic Beanstalk
- AWS Auto Scaling
- AWS CloudFormation
- AWS Identity and Access Management
- AWS Organizations

A CloudFormation stack is free; the EC2 instances it creates are not. A VPC is free; the NAT gateway inside it is emphatically not.

[The source notes also listed AWS OpsWorks here. AWS OpsWorks Stacks reached end of life on May 26, 2024 and has been disabled for both new and existing customers, with OpsWorks for Chef Automate ending May 5, 2024 and OpsWorks for Puppet Enterprise ending March 31, 2024. AWS Systems Manager is the recommended replacement.]

---

## 15.2 What Actually Drives a Bill

Three cost drivers account for most of an AWS invoice.

**Compute**

- Billed per hour or per second, with per-second billing applying to many Linux-based instances.
- The rate varies by instance family and size, Region, tenancy, operating system, architecture, and purchase option.

**Storage**

- Typically billed per GB-month of provisioned or stored data.
- Some services add charges for requests, throughput, retrieval, or provisioned IOPS.
- Storage bills whether or not the data is read, and whether or not a volume is attached.

**Data transfer**

- Inbound transfer is generally free.
- Outbound transfer is charged, with rates varying by destination: the internet, another Availability Zone, or another Region.
- Rates are usually tiered per GB.

Data transfer is the line most often missed at design time, because it does not appear in an instance's hourly rate and only shows up once traffic is real. Section 30.3 covers designing around it.

---

## 15.3 The AWS Pricing Calculator

The AWS Pricing Calculator at https://calculator.aws models cost before anything is built.

**What it is used for**

- Estimating monthly cost for a proposed architecture.
- Comparing On-Demand, Reserved Instance, and Savings Plans scenarios side by side.
- Identifying reduction opportunities through right-sizing and commitment discounts.
- Naming, grouping, and sharing an estimate for review.

**Building an estimate**

1. Open https://calculator.aws.
2. Choose **Create estimate**.
3. Choose **Add service** and search for the service to model.
4. Set the Region.
5. Enter the workload parameters, such as instance type, quantity, hours per month, and storage volume.
6. Choose **Add to my estimate**.
7. Repeat for each service in the architecture.
8. Group related services into components so the estimate is readable.
9. Choose **Share** to produce a link for stakeholders.

![AWS Pricing Calculator showing a service estimate](https://github.com/user-attachments/assets/3a88bd1c-efe0-4346-9a35-6d08347c7859)

**Reading the output**

- **First 12 months total** includes amortized upfront commitments.
- **Total upfront** is the one-time payment for reservations or Savings Plans.
- **Total monthly** is the recurring charge once any upfront payment is made.

---

## 15.4 Consolidated Billing with AWS Organizations

AWS Organizations is defined in section 8.7, which covers organizational units and service control policies. This section covers the billing side only.

- **Consolidated billing** produces a single bill across every account in the organization, paid by the management account.
- **Volume discounts aggregate.** Tiered pricing is calculated across the organization's combined usage rather than per account, so a group of small accounts reaches a lower per-unit rate together than any of them would alone.
- **Reserved Instances and Savings Plans are shared.** Unused reserved capacity in one account can apply to matching usage in another, which prevents commitments going to waste.
- **Cost visibility per account** remains, so each team's spend is still attributable even though one invoice is issued.
- AWS Organizations itself is free.

**Other organization-level policies** that appear on the exam alongside SCPs:

| Policy type | Purpose |
| --- | --- |
| Tag policies | Enforce consistent tag keys and value capitalization across accounts |
| Backup policies | Standardize AWS Backup plans across accounts |
| AI services opt-out policies | Centrally opt out of AWS using your content to improve AI services |

**Delegated administrator** lets a member account administer a specific service, such as GuardDuty, Security Hub, or AWS Config, across the whole organization without holding full access to the management account. This is a common exam answer for "give one account org-wide control of one service."

---

## 15.5 The Billing and Cost Management Console

| Tool | Purpose |
| --- | --- |
| AWS Budgets | Cost, usage, Reserved Instance, and Savings Plans budgets with threshold alerts by email or SNS |
| AWS Cost Explorer | Interactive analysis with filtering and grouping, plus right-sizing and commitment recommendations |
| AWS Cost and Usage Report | The most detailed line-item data, delivered to S3 and queryable with Athena, Redshift, or QuickSight |
| Cost Anomaly Detection | Machine learning detection of unusual spend, with alerting |
| Cost allocation tags | Attribute cost to a team, project, or environment |
| AWS Billing Conductor | Custom billing groups for chargeback and showback |

**The billing dashboard** shows month-to-date and forecast charges, spend broken down by service, payment methods, invoices and tax settings, and credit balances, and links directly to Cost Explorer, Budgets, and the reservation purchasing console.

![Billing and Cost Management dashboard showing month-to-date spend](https://github.com/user-attachments/assets/7f7e85b5-44d5-4456-915d-a75731bc5336)

![Cost breakdown by service in the billing console](https://github.com/user-attachments/assets/4dd47a09-99f3-4234-969a-d2f23746fc5b)

![Cost Explorer showing spend trends over time](https://github.com/user-attachments/assets/4e99e4fa-3d75-4fbd-b2f8-334448a2f0e7)

A note on tagging: cost allocation tags must be activated in the billing console before they appear in reports, and they only apply from the point of activation onward. Tagging retrospectively does not recategorize past spend.

---

## 15.6 Cost Optimization Levers

Ordered roughly by typical impact.

**Right-size first.** Cost Explorer right-sizing recommendations and AWS Compute Optimizer identify overprovisioned resources. This is usually the largest and least disruptive saving available.

**Commit to steady-state usage.** Savings Plans or Reserved Instances for the baseline that always runs. Savings Plans are preferred for new commitments because they apply across instance families and to Lambda and Fargate.

**Use Spot for interruptible work.** Batch processing, CI/CD, and big data jobs at up to 90% off On-Demand.

**Schedule non-production environments off.** Development and test environments running nights and weekends are pure waste.

**Optimize storage.**

- S3 lifecycle policies to move objects into cheaper classes.
- EBS gp3 rather than gp2, which is cheaper per GB with a better baseline.
- EFS Infrequent Access for cold file data.
- Delete unattached volumes, stale snapshots, and unassociated Elastic IP addresses.

**Reduce data transfer.**

- Keep traffic within an Availability Zone where the architecture allows.
- Use VPC interface endpoints so traffic to AWS services avoids NAT gateway processing charges.
- Put CloudFront in front of an origin to cut egress.
- Weigh multi-Region replication against its transfer cost rather than assuming it.

**Make cost visible.** Consistent tagging, Cost Anomaly Detection configured early, and someone who owns the monthly review. Without ownership none of the above happens twice.

---

## 15.7 AWS Support Plans

This is the area of Part II most affected by recent change. Learn the current plans, because exams and existing accounts still use them, and learn the transition, because it is already under way.

### 15.7.1 The Current Plans

| Plan | Intended for | Initial response targets | Included |
| --- | --- | --- | --- |
| Basic | All accounts, free | Community forums only | Documentation, AWS re:Post, AWS Health Dashboard, a subset of Trusted Advisor checks |
| Developer | Development and test | General guidance under 24 business hours; system impaired under 12 business hours | Business-hours email support, limited architectural guidance |
| Business | Production workloads | Production system down under 1 hour; production system impaired under 4 hours | 24/7 phone, chat, and email, full Trusted Advisor checks, AWS Support API |
| Enterprise On-Ramp | Growing production estates | Business-critical system down under 30 minutes | 24/7 support, a pool of technical account managers, AWS Countdown |
| Enterprise | Mission-critical workloads | Business-critical system down under 15 minutes | Designated technical account manager, concierge support, operations and Well-Architected reviews |

Response times are targets for **initial response**, not resolution. Confirm current commitments at https://aws.amazon.com/premiumsupport/plans/.

### 15.7.2 The 2027 Transition

Verified against AWS Support documentation:

- **Developer Support, Business Support, and Enterprise On-Ramp are all discontinued on January 1, 2027.**
- Enterprise On-Ramp customers are automatically upgraded to Enterprise Support through 2026, at contract renewal or in periodic batches, with an email notification about a month beforehand.
- The replacement plans are **Business Support+**, at a $29 per month minimum per account, **Enterprise Support**, at a $5,000 per month minimum reduced from $15,000, and **Unified Operations**.
- Enterprise Support includes a designated technical account manager, 15-minute response for production-critical cases, and AWS Security Incident Response at no additional cost.
- Developer, Business, and Enterprise On-Ramp remain available in the AWS GovCloud (US) Regions.

For the exam, expect the five current plan names. For real accounts, plan around the replacements.

---

## 15.8 Getting Help

**AWS Trusted Advisor** runs automated best-practice checks across cost optimization, performance, security, fault tolerance, service limits, and operational excellence.

| Category | Example checks |
| --- | --- |
| Cost optimization | Idle EC2 instances, underutilized reservations |
| Performance | Service limit headroom, optimization opportunities |
| Security | MFA on the root user, publicly accessible S3 buckets, open security groups |
| Fault tolerance | Auto Scaling configuration, backup coverage |
| Service limits | Warnings before an account limit is reached |
| Operational excellence | Operational best-practice checks |

Access by plan is covered in section 13.6. Basic accounts get all Service Limits checks plus selected Security and Fault Tolerance checks, refreshed manually; full access requires Business Support+, Enterprise Support, or Unified Operations.

[The source notes stated that seven core checks are free on all plans. That figure predates the current check set and is no longer accurate.]

**AWS Health Dashboard** shows service health by Region and events affecting your own account and resources, as covered in section 13.7.

**Technical account manager** is a designated advisor on Enterprise Support, covering roadmap alignment, proactive reviews, and escalation management.

**AWS Countdown**, formerly Infrastructure Event Management, provides dedicated support for planned events such as launches, migrations, and seasonal peaks.

**AWS Support Center** is where cases are raised. Case categories are account and billing, service limit increases, and technical support, the last of which requires a paid plan.

**AWS re:Post** is the community question and answer service and is available to every account.

---

## 15.9 End-of-Chapter Questions

**Q1.** Which AWS service provides automated recommendations across cost, performance, security, fault tolerance, and service limits?

- A. AWS Cost Explorer
- B. AWS CloudTrail
- C. AWS Trusted Advisor
- D. Amazon CloudWatch

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* The keyword is recommendations; Cost Explorer analyzes spend, CloudTrail records API calls, and CloudWatch reports metrics.

**Q2.** A company will run a steady-state production workload continuously for three years on a fixed instance type. Which purchase option gives the deepest discount?

- A. On-Demand Instances
- B. Spot Instances
- C. All Upfront Reserved Instances on a three-year term
- D. Dedicated Hosts

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* All Upfront on the longest term is the deepest commitment discount; Spot is cheaper per hour but can be interrupted, which rules it out for continuous production.

**Q3.** Which statement correctly describes a service control policy in AWS Organizations?

- A. It grants permissions to IAM users in member accounts
- B. It overrides IAM policies to allow actions that IAM denies
- C. It sets the maximum permissions available to accounts or organizational units but grants nothing
- D. It applies only to the management account

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* An SCP is a ceiling. Both the SCP and an IAM policy must allow an action, and SCPs do not apply to the management account.

**Q4.** A company operates 30 AWS accounts and wants one invoice, shared reserved capacity, and combined volume pricing tiers. Which feature provides this?

- A. Cost allocation tags
- B. Consolidated billing through AWS Organizations
- C. AWS Budgets
- D. AWS Billing Conductor

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* Consolidated billing aggregates usage for tiered pricing and shares reservations across member accounts under a single payer.

**Q5.** An architect needs one member account to manage Amazon GuardDuty findings for every account in the organization, without granting it administrative access to those accounts. Which feature supports this?

- A. A service control policy
- B. Delegated administrator
- C. Consolidated billing
- D. A tag policy

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Delegated administrator designates a member account as the administrator for one specific service across the organization.

**Q6.** A team's monthly bill is dominated by NAT gateway data processing charges from EC2 instances calling Amazon S3. What is the most effective change?

- A. Move the instances to a larger instance type
- B. Purchase a Savings Plan for the NAT gateway
- C. Create a gateway VPC endpoint for S3 so the traffic bypasses the NAT gateway
- D. Enable S3 Intelligent-Tiering on the bucket

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Gateway endpoints for S3 carry no hourly or per-GB charge and route traffic off the NAT path entirely; Savings Plans do not apply to NAT gateways.
