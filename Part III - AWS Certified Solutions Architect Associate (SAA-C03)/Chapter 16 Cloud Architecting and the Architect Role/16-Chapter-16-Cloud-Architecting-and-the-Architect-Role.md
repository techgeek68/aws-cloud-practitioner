# Chapter 16: Cloud Architecting and the Architect Role

---

Part II asked what each AWS service does. Part III asks a different question: given a set of business requirements and constraints, which combination of services is the right answer, and what are you giving up by choosing it?

That shift is the whole of the Solutions Architect Associate exam. Questions describe a situation and offer four options, of which two or three will work and only one is best under the stated constraint. Learning to find the constraint is as important as knowing the services.

**Prerequisites for this part**

- The material in Part II, or equivalent knowledge.
- Working familiarity with distributed systems, multi-tier architectures, and general networking.

---

## 16.1 What Cloud Architecture Is

Cloud architecture is the practice of applying cloud characteristics, through cloud services and features, to meet an organization's technical needs and business use cases.

Two halves of that definition matter equally:

- **Technical needs** are latency targets, throughput, availability, recovery objectives, and data residency.
- **Business use cases** are what the organization is trying to achieve, on what budget, by when.

An architecture that satisfies the first and ignores the second is a design nobody will fund. The reverse is a design that will not survive contact with production.

**The construction analogy**

| Construction | Cloud |
| --- | --- |
| Customer, who decides | Business stakeholders |
| Architect, who designs | Cloud architect |
| Building crew, who delivers | DevOps and engineering teams |

The architect does not lay bricks and does not sign the cheque. The role is to turn what the customer wants into something the crew can build, and to be accountable for whether the result stands up.

---

## 16.2 The Cloud Architect Role

The work divides into three phases, which repeat per project rather than running once.

**Plan**

- Set technical cloud strategy with business leaders.
- Analyze proposed solutions against business requirements.
- Translate business needs into technical requirements.

**Research**

- Investigate AWS service specifications and quotas.
- Review the architecture of existing workloads.
- Design and test prototype solutions.
- Evaluate whether the chosen services actually work together as intended.

**Build**

- Design a transformation roadmap with milestones.
- Define work streams and who owns each.
- Manage adoption and migration.

**Ongoing responsibilities**

- Keep current with new services and features, since the correct answer changes as AWS releases capability.
- Provide documentation, patterns, and tooling that let developers move quickly without reinventing decisions.
- Resolve challenges against best practice across cost, performance, reliability, and security.

Common job titles: Cloud Architect, Solutions Architect, Systems Engineer, Systems Analyst.

---

## 16.3 Roles Around the Architect

Understanding who does what prevents the most common failure in cloud projects, which is an architecture nobody owns.

| Role | Focus | Typical titles |
| --- | --- | --- |
| IT professional | Generalist managing applications and production environments; highly technical, cloud experience varies | IT Administrator, Systems Administrator, Network Administrator |
| IT leader | Team leadership, day-to-day operations, budget, technology selection; hands-on early then delegating | IT Manager, IT Director, IT Supervisor |
| Developer | Writing, testing, and fixing code; thinks at application level; works with APIs and SDKs | Software Developer, Software Development Manager |
| DevOps engineer | Builds the infrastructure to the architect's guidelines and experiments to improve deployment | DevOps Engineer, Build Engineer, Reliability Engineer |
| Cloud architect | Strategy, design, and the trade-offs between the pillars | Cloud Architect, Solutions Architect |

---

## 16.4 The SAA-C03 Exam at a Glance

The AWS Certified Solutions Architect Associate exam validates the ability to design solutions using the AWS Well-Architected Framework, incorporate services to meet current and future business needs, and review existing solutions to identify improvements.

**Domains and weightings**

| Domain | Content | Weight |
| --- | --- | --- |
| 1 | Design Secure Architectures | 30% |
| 2 | Design Resilient Architectures | 26% |
| 3 | Design High-Performing Architectures | 24% |
| 4 | Design Cost-Optimized Architectures | 20% |

**Format**

- 65 questions, multiple choice and multiple response.
- 130 minutes.
- Scaled score from 100 to 1,000.
- [The published minimum passing score of 720 could not be confirmed in the current official exam guide; confirm on the AWS certification page.]

**Where each domain is covered in this course**

| Domain | Chapters |
| --- | --- |
| Design Secure Architectures | 17, plus 21 for network security |
| Design Resilient Architectures | 23, 26, 29 |
| Design High-Performing Architectures | 18, 19, 20, 22, 24, 28 |
| Design Cost-Optimized Architectures | 30, building on 10 and 15 |

Note that the weightings and the chapter count do not match, deliberately. Secure Architectures is the heaviest domain at 30% but is concentrated in fewer chapters, because much of the underlying material was established in Chapter 8.

---

## 16.5 The Running Case Study

Part III uses one continuing scenario, so that each design decision has a context rather than existing in isolation.

**The business.** Prakash and Maya opened a cafe and bakery in retirement. The business is growing, and they are adopting cloud technology to keep up with it. They are assisted by AWS consultants who are also regular customers.

**The people**

| Person | Role |
| --- | --- |
| Prakash | Co-owner, retired from the navy, bakes, nontechnical |
| Maya | Co-owner, retired accountant, strong with spreadsheets, nontechnical |
| Sushma | Their daughter, supply chain manager, has programming skills |
| Nikhil | Employee with visual design skills, interested in cloud computing |
| Anjali | AWS Solutions Architect, database and network specialist |
| Sita | Developer, AWS programming interfaces and cloud security |
| Rajan | Systems Administrator, automation, backups, disaster recovery |

**How it is used.** The cafe's architecture evolves across the chapters: a static website, then a storage layer, a compute layer, a database, a proper network, monitoring and scaling, caching, decoupling, and finally a disaster recovery plan. Each step is driven by a business problem rather than by a wish to use a service.

The value of the scenario is the constraints it imposes. Two nontechnical owners and one part-time developer cannot operate a self-managed Kubernetes cluster, whatever its technical merits. Recognizing when the right technical answer is the wrong answer for the organization is a large part of the job.

---

## 16.6 Applying the Well-Architected Framework

The six pillars are defined in section 6.5. This section turns them into questions to ask about a specific design.

| Pillar | Questions to ask of a design | Where covered in Part III |
| --- | --- | --- |
| Operational Excellence | Is the infrastructure defined as code? Can it be rebuilt from that definition? How would an operator know something is wrong? | 27 |
| Security | Is least privilege actually enforced? Is data encrypted in transit and at rest? Is every action traceable? | 17, 21 |
| Reliability | What happens when this component fails? What are the RTO and RPO? Has recovery been tested? | 23, 29 |
| Performance Efficiency | Does the instance type match the workload profile? Where is the bottleneck? Would a managed service be faster to the same outcome? | 18, 19, 24 |
| Cost Optimization | What does this cost per month? What is running that nobody uses? Which commitment applies? | 30 |
| Sustainability | Is utilization high or is capacity idle? Is data retained that nobody reads? | 30 |

### 16.6.1 Design Trade-Offs

The pillars conflict, and the framework exists to make the conflict explicit. Examples of trade-offs an architect makes deliberately:

- Trade consistency or durability for latency, where the application can tolerate it.
- Trade cost for speed to market on a new feature whose demand is unproven.
- Trade operational control for reduced effort by choosing a managed service.

Base these on measurement rather than instinct. A design decision defended by "it felt faster" is not defensible.

### 16.6.2 Ten Best Practices

These recur throughout Part III and are worth holding as a checklist.

1. **Implement scalability.** Design for elastic scaling with Auto Scaling groups and load balancers rather than provisioning for peak.
2. **Automate with infrastructure as code.** Duplicate environments quickly, reduce configuration error, and propagate change consistently.
3. **Treat resources as disposable.** Provision dynamically, automate identical configuration, stop what is unused, and test updates on new resources before replacing old ones.
4. **Couple components loosely.** Independent components limit how far a failure propagates. Queues and topics decouple producers from consumers.
5. **Design services, not servers.** Reach for containers, serverless, and managed services before defaulting to EC2.
6. **Select the database by workload.** Ask about read and write balance, total size, access pattern, durability requirements, latency, concurrency, query complexity, and whether ACID compliance is required.
7. **Avoid single points of failure.** Assume everything fails and design backward from there: multi-AZ, standby replicas, automatic failover.
8. **Optimize for cost.** Right-size, monitor the metrics that matter, shut down what is unused, and replace self-managed servers with managed services where the arithmetic works.
9. **Cache where it pays.** Remove redundant retrieval with CloudFront, ElastiCache, or DAX.
10. **Apply defense in depth.** Managed services, logged access, isolated components, encryption in transit and at rest, least privilege, MFA, and automated deployment for consistency.

---

## 16.7 Resource Placement Decisions

The infrastructure hierarchy from Chapter 5 becomes a design decision here.

```
Global > Regions > Availability Zones > Data Centers
                 > Edge Locations
```

**Choosing a Region.** Work through compliance, latency, service availability, and cost in that order, as covered in section 5.1.1. Compliance eliminates Regions outright; the rest narrow the remainder.

**Using Availability Zones.** Distribute every tier across at least two. This is the cheapest availability improvement available and the one exam questions expect by default.

**Extending beyond a Region.** Local Zones for metropolitan latency, Wavelength Zones for 5G devices, Outposts for on-premises data residency, and edge locations for cached content. Section 5.5 covers the distinctions.

**Placement questions to answer for any design**

- Which Region, and what makes it the only acceptable one?
- How many Availability Zones, and is every tier spread across them?
- Does any component need to sit closer to users than a Region allows?
- Does any data physically have to stay somewhere specific?

---

## 16.8 How to Read a Scenario Question

SAA-C03 questions are long, and most of the length is context rather than information. A method that works:

1. **Read the last sentence first.** It states what is actually being asked, which is often narrower than the scenario suggests.
2. **Find the constraint.** Almost every question contains one qualifying word that eliminates two options: *most cost-effective*, *least operational overhead*, *minimum downtime*, *without changing the application*, *fewest changes*.
3. **Eliminate what does not work at all.** Usually one option is technically wrong rather than merely suboptimal.
4. **Choose between the remainder on the constraint,** not on which is technically most impressive.

**Distractor patterns to recognize**

- An option that solves the problem but requires managing servers, when the question asked for least operational overhead.
- An option using a service that fits the general area but not the specific requirement, such as a read replica offered where automatic failover was asked for.
- An option that is correct but for a different scale, such as multi-Region replication offered for a requirement that multi-AZ satisfies.
- An option naming a real service doing something it does not do.

**Time management.** 65 questions in 130 minutes is two minutes each. Flag anything taking longer than three minutes and return to it. Unanswered questions score zero, so guess on everything before the clock runs out.

---

## 16.9 End-of-Chapter Questions

**Q1.** Which of the following best describes the role of a cloud architect?

- A. Writing and testing application code against AWS APIs
- B. Translating business requirements into technical designs and managing the trade-offs between cost, performance, reliability, and security
- C. Building and operating infrastructure to a specification provided by others
- D. Approving budgets for cloud projects

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Option A describes a developer, C a DevOps engineer, and D an IT leader; the architect sits between the business requirement and the technical design.

**Q2.** Which domain carries the highest weighting on the SAA-C03 exam?

- A. Design Resilient Architectures
- B. Design High-Performing Architectures
- C. Design Secure Architectures
- D. Design Cost-Optimized Architectures

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Secure Architectures accounts for 30%, ahead of Resilient at 26%, High-Performing at 24%, and Cost-Optimized at 20%.

**Q3.** A design review finds that a workload meets its performance target but has no way for operators to detect failure, and its infrastructure exists only as manually created resources. Which two Well-Architected pillars does this most directly fail?

- A. Cost Optimization and Sustainability
- B. Operational Excellence and Reliability
- C. Security and Performance Efficiency
- D. Reliability and Cost Optimization

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Operations as code and the ability to detect failure sit under Operational Excellence, and the inability to recover reliably from a failure nobody notices sits under Reliability.

**Q4.** An architect proposes a self-managed Kubernetes cluster for a small business with two nontechnical owners and one part-time developer. The design is technically sound. What is the strongest objection?

- A. Kubernetes cannot run on AWS
- B. The design ignores the organization's capacity to operate it, which is a business requirement as real as any technical one
- C. Containers are unsuitable for small workloads
- D. The cost of EKS exceeds that of EC2

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Architecture is the fit between requirement and capability; an operationally correct design the organization cannot run is not a correct design.
