# Chapter 5: AWS Global Infrastructure

---

The AWS global infrastructure is built in three primary layers, plus a set of extensions for cases the standard layers do not cover.

- **Regions** are geographic areas and the boundary for isolation and compliance.

- **Availability Zones** sit inside Regions and are the boundary for fault isolation.

- **Points of Presence** form the global edge network that brings content close to users.

- **Local Zones, Wavelength Zones, and AWS Outposts** extend AWS infrastructure to places a Region cannot reach.

Almost every availability and compliance decision in the rest of this course comes back to this chapter, so it is worth learning properly rather than memorizing.

Reference links, which carry live figures:

- Global infrastructure map: https://aws.amazon.com/about-aws/global-infrastructure/

- Regions and Availability Zones: https://aws.amazon.com/about-aws/global-infrastructure/regions_az/

- Regional service availability: https://aws.amazon.com/about-aws/global-infrastructure/regional-product-services/

---
## 5.1 Regions

A Region is a distinct geographic area containing multiple, physically separated Availability Zones.

- Regions are isolated from one another. This is deliberate: a failure in one Region does not propagate to another, and it creates a clean boundary for data residency.

- **Data does not leave a Region unless you configure it to.** Replication, backup copies, and cross-Region services are all explicit choices you make. Nothing moves implicitly.

- Traffic between Regions travels over the AWS private backbone rather than the public internet, which makes performance more predictable.

- Not every service, instance type, or feature is available in every Region. New capabilities usually launch in a subset of Regions first.

- Pricing differs between Regions. The same instance type can cost noticeably more in one Region than another.

- Each Region has a code used everywhere in the CLI, SDKs, and ARNs, such as `us-east-1` for US East (N. Virginia) and `eu-west-1` for Europe (Ireland).

> AWS operates well over 35 Regions and continues to add more, with additional Regions announced including Chile and the AWS European Sovereign Cloud. Exact counts change too often to state reliably in a course document, so check the global infrastructure page for current figures.

### 5.1.1 Choosing a Region

Four factors decide it, and they are easy to remember as compliance, latency, availability, and spend.

| Factor | What it means |
| --- | --- |
| Compliance | Data residency law, contractual obligations, and governance requirements. This factor is usually absolute: if regulation requires data to stay in a country, no other factor can override it. |
| Latency | Physical proximity to your users. Distance is the one thing you cannot engineer away. |
| Available services | Whether the Region offers the services, instance types, and features the design depends on. |
| Spend | Regional price differences, which can be material at scale. |

Work through them in that order. Compliance eliminates Regions outright, latency narrows the list to a sensible geography, service availability removes any Region that cannot run the design, and cost decides between whatever remains.

Two practical points that catch people out:

- **Some services are global rather than regional.** IAM, Amazon Route 53, Amazon CloudFront, and AWS WAF for CloudFront are not selected per Region. Billing data is also global.

- **`us-east-1` has a special role.** Certain global operations, such as some CloudFront certificate requests and some billing APIs, must be performed there regardless of where your workload runs.

---
## 5.2 Availability Zones

An Availability Zone is one or more discrete data centers within a Region, each with independent power, cooling, networking, and physical security.

- AWS states that each Region consists of a minimum of three isolated and physically separate Availability Zones. [One documented exception exists: newer accounts can access two Availability Zones in US West (N. California). Check the AWS Regions and Availability Zones documentation for current per-Region detail.]

- Zones are separated by a meaningful physical distance, far enough that a single fire, flood, or power event should not affect more than one. [AWS publishes geographic detail per zone, but a specific separation distance is not something to quote without checking the current documentation.]

- Zones within a Region are connected by redundant, high bandwidth, low-latency links, typically single-digit milliseconds round trip. That is fast enough for synchronous replication between zones, which is what makes Multi-AZ database deployments practical.

- Availability Zone names are mapped independently per account. Your `us-east-1a` is not necessarily the same physical zone as another account's `us-east-1a`. AWS provides a zone ID, such as `use1-az1`, when the physical zone actually matters, for example when sharing resources across accounts.

**The design rule that follows from all of this**

Distribute every tier of an application across at least two Availability Zones. This is the single highest-value availability decision available, it costs little beyond inter-zone data transfer, and it is what services such as Elastic Load Balancing, EC2 Auto Scaling, and RDS Multi-AZ exist to make straightforward.

A useful way to hold the distinction: choose a Region for compliance, latency, and cost, and use multiple Availability Zones for resilience.

---
## 5.3 Data Centers

You never interact with a data center directly, and AWS does not disclose their locations at street level. What matters for the exams and for architecture conversations is what the design guarantees.

- Purpose built facilities with redundant power feeds, uninterruptible power supplies, backup generators, and diverse fiber paths into the building.

- Layered physical security: perimeter controls, video surveillance, badge and biometric access control, and continuous auditing.

- Every layer is designed to fail without taking the zone with it, which is what allows AWS to state that a zone failure should not affect other zones.

- [Individual data centers are commonly described as holding tens of thousands of physical servers. AWS does not publish a per-facility figure, so treat any specific number as approximate.]

The point to take away is that physical security and facility resilience are AWS responsibilities, not yours. That division is formalized in the shared responsibility model in section 8.1.

---
## 5.4 Points of Presence

Points of Presence are the edge network. They comprise edge locations and regional edge caches, and they sit far closer to end users than any Region does.

**Edge locations**

- Cache content and terminate connections close to users, which cuts the round-trip distance for requests.

- Number in the hundreds, across many more cities and countries than there are Regions.

**Regional edge caches**

- Sit between edge locations and your origin.

- Hold less frequently accessed content for longer than an edge location would, so that a cache miss at the edge often does not have to reach the origin at all.

- Reduce load on the origin and improve overall cache hit ratio.

**What the edge network powers**

- **Amazon CloudFront:** content delivery, covered in section 9.7 and Chapter 24.

- **Amazon Route 53:** DNS resolution answered from the nearest location, covered in section 9.6.

- **AWS Global Accelerator:** moves traffic onto the AWS backbone as early as possible, covered in section 23.6.

- **AWS Shield and AWS WAF:** absorb and filter attacks at the edge, before traffic reaches your Region.

> Point of Presence counts are published on the CloudFront and global infrastructure pages and change frequently, so check there rather than relying on a figure printed in study material.

---
## 5.5 Extended Infrastructure

Three extensions exist for workloads that a standard Region cannot serve well.

| Infrastructure | What it is | Latency target | Typical use |
| --- | --- | --- | --- |
| AWS Local Zones | An extension of a parent Region placed in a major metropolitan area, offering a subset of AWS services | Single-digit milliseconds to users in that city | Live video production, real-time gaming, virtual desktops, media rendering |
| AWS Wavelength Zones | AWS compute and storage deployed inside a telecommunications provider's 5G network | Very low latency to devices on that mobile network | Connected vehicles, augmented and virtual reality, industrial IoT |
| AWS Outposts | AWS-managed racks installed in your own data center or colocation facility, running AWS services locally and connected to a parent Region | On-premises latency | Strict data residency, proximity to on-premises systems, local data processing |

Points that distinguish them, and that exams test:

- **Local Zones serve users reaching you over the internet in a specific city.** Wavelength Zones serve devices on a mobile carrier's 5G network. That is the difference, and it is the one most often confused.

- **Outposts is the hybrid option.** It puts AWS hardware in your building. Same APIs, same tooling, your floor space. It is the answer when data physically cannot leave a site.

- All three are extensions of a parent Region, not independent Regions, and all three offer a subset of AWS services rather than the full catalog.

---
## 5.6 Regions vs Availability Zones vs Edge Locations

| Aspect | Region | Availability Zone | Edge location |
| --- | --- | --- | --- |
| Purpose | Geographic isolation and compliance boundary | Fault isolation within a Region | Low-latency delivery and network optimization |
| Composed of | A set of Availability Zones | One or more data centers | A caching and connection endpoint |
| Typical use | Multi-Region disaster recovery, data residency | Distributing application tiers and replicas | CloudFront caching, Route 53 DNS, DDoS absorption |
| Data movement | Explicit only, through replication or copy | Automatic for some services, for example S3 storing across zones | Cached copies, fetched from origin on a miss |
| Latency profile | Higher between Regions | Low between zones, single-digit milliseconds | Lowest to the end user |
| You choose it | Yes, per resource | Yes, for most compute and storage resources | No, AWS routes to the nearest one |

**The design sequence**

1. Build for multi-AZ resilience first. It handles the failures that actually happen, and it is inexpensive.
2. Add edge delivery when users are geographically spread and content can be cached.
3. Extend to multi-Region only when disaster recovery targets, regulatory segmentation, or global write latency require it. Multi-Region is a significant increase in cost and complexity, covered in Chapter 29.

---
## 5.7 Infrastructure Characteristics

These four terms are used constantly across both exams and throughout the rest of this course. They are related but not interchangeable, and questions are often written to test exactly that.

| Characteristic | Definition | AWS example |
| --- | --- | --- |
| Scalability | The ability to increase or decrease capacity to meet demand | Adding instances to an Auto Scaling group, or moving to a larger instance type |
| Elasticity | Doing that automatically, in response to actual demand, without human involvement | An Auto Scaling group adding instances when average CPU crosses a target |
| Fault tolerance | Continuing to operate correctly despite a component failure | RDS Multi-AZ failing over to the standby without application changes |
| High availability | Minimizing downtime through redundancy and automated recovery | An Application Load Balancer routing only to healthy targets across two zones |

Distinctions worth being precise about:

- **Scalability is a capability; elasticity is automation of it.** Manually resizing an instance is scaling. It is not elasticity.

- **Fault tolerance is stronger than high availability.** A highly available system recovers quickly from failure, usually with a brief interruption. A fault-tolerant system absorbs the failure without interruption. Fault tolerance costs more, because it requires redundant capacity running all the time.

- **Vertical scaling means a bigger instance; horizontal scaling means more instances.** Vertical scaling has a ceiling and usually requires a restart. Horizontal scaling is the cloud native approach and is what makes elasticity possible.

**How the infrastructure delivers each one**

- Multiple Availability Zones per Region provide the physical separation that fault tolerance and high availability require.

- Elastic Load Balancing and EC2 Auto Scaling provide the automation, covered in Chapter 13.

- Amazon S3 stores objects redundantly across a minimum of three Availability Zones in most storage classes. The exception is S3 One Zone-Infrequent Access, which stores data in a single zone and therefore does not survive the loss of that zone.

- Service level agreements are published per service and are the formal statement of what AWS commits to, though an SLA is a commercial commitment and not a substitute for designing across zones.

---
## 5.8 Activity: Confirm Scope in the Console

Region and Availability Zone scope is easier to remember once you have seen it in the console rather than only read about it. This activity takes about ten minutes and creates nothing, so there is no cleanup and no cost.

1. Sign in to the AWS Management Console.
2. Open the **Services** menu and look at how services are grouped into categories. Amazon EC2, for example, sits under **Compute**.

---
![Services menu showing AWS services grouped into categories](https://github.com/user-attachments/assets/0679a0aa-57ff-407a-9ced-7b1a7641e0cc)

---
3. Find IAM in the services list and note its category.

   **Which category is IAM in?** Security, Identity, and Compliance.

---
![IAM listed under the Security, Identity, and Compliance category](https://github.com/user-attachments/assets/37c61ed2-ec57-4cdd-b562-2014fcf6763c)

---
4. Find Amazon VPC and note its category.

   **Which category is Amazon VPC in?** Networking and Content Delivery.

---
![Amazon VPC listed under the Networking and Content Delivery category](https://github.com/user-attachments/assets/da8e353f-c282-4acc-abc3-1805cbd753cb)

---
5. Open the **VPC** console and choose **Subnets**. Look at the **Availability Zone** column.

   **Does a subnet exist at Region or Availability Zone level?** Availability Zone level. A subnet is always tied to exactly one zone, which is why spanning two zones means creating two subnets.

---
![Subnets list showing an Availability Zone column](https://github.com/user-attachments/assets/9fe51f43-a99f-4aa6-aaf8-0bfef7c65d4b)

---
6. Choose **Your VPCs** and look for an Availability Zone column.

   **Does a VPC exist at Region or Availability Zone level?** Region level. A VPC spans every zone in its Region, and the subnets inside it are then scoped to individual zones.

---
![Your VPCs list showing no Availability Zone column](https://github.com/user-attachments/assets/a73e4b26-14a3-4e44-a794-044429e1f45d)

---
7. Change the Region in the Region selector and revisit EC2, IAM, Lambda, and Route 53 in turn.

   **Which of these are global?** IAM and Route 53 are global, so their contents look identical whichever Region is selected. EC2 and Lambda are Regional, so their resource lists change with the Region.

---
![EC2 console showing Regional resources](https://github.com/user-attachments/assets/f6ffb444-4710-44a2-b64a-a052a66706fb)

---
![IAM console showing global scope regardless of selected Region](https://github.com/user-attachments/assets/b0546e22-7a43-44ca-966f-e76574ec19d1)

---
![Route 53 console showing global scope regardless of selected Region](https://github.com/user-attachments/assets/b8eaa4d5-325d-4760-90bb-98da5ddc5dfe)

---
The pattern to take away: identity and DNS are global, and almost everything that costs money by the hour is Regional.

---
## 5.9 End of Chapter Questions

**Q1.** A company must ensure that customer data never leaves its country for regulatory reasons. Which element of the AWS global infrastructure primarily addresses this?

- A. Availability Zones
- B. Regions
- C. Edge locations
- D. Regional edge caches

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* Regions are isolated geographic boundaries, and data does not move between them unless the customer explicitly configures it to.

**Q2.** An application runs on EC2 instances in a single Availability Zone. What is the most effective change to improve its availability?

- A. Move the instances to a larger instance type
- B. Deploy instances across at least two Availability Zones behind a load balancer
- C. Deploy a copy of the application in a second Region
- D. Enable Amazon CloudFront in front of the application

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* Multi-AZ deployment removes the single zone as a point of failure at low cost and complexity, which is the first availability step in any AWS design.

**Q3.** A gaming company needs single-digit millisecond latency to players connecting from a specific large city, where AWS has no Region. Which option fits best?

- A. AWS Outposts
- B. AWS Wavelength Zone
- C. AWS Local Zone
- D. A regional edge cache

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* Local Zones extend a parent Region into metropolitan areas to serve users in that city, whereas Wavelength Zones target devices inside a 5G carrier network.

**Q4.** Which statement correctly distinguishes elasticity from scalability?

- A. They are different names for the same capability
- B. Scalability is the ability to change capacity; elasticity is doing so automatically in response to demand
- C. Elasticity applies only to storage, and scalability only to compute
- D. Scalability requires multiple Regions; elasticity requires multiple Availability Zones

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* Elasticity is the automation of scaling, which is why an Auto Scaling group is elastic and a manual instance resize is not.

**Q5.** An architect selects S3 One Zone-Infrequent Access for a set of derived data files to reduce cost. Which risk must be accepted?

- A. Objects become unavailable if the single Availability Zone storing them is lost
- B. Objects cannot be encrypted at rest
- C. Objects cannot be retrieved without a restore request
- D. Objects are automatically deleted after 30 days

**Answer: A.** *Target exam: AWS Certified Solutions Architect - Associate.* One Zone-IA stores data in a single Availability Zone, so it is appropriate only for data that can be regenerated or that exists elsewhere.

**Q6.** A workload must be deployed to a Region that satisfies a data residency requirement, but the design depends on a specialized instance type. What should the architect verify before committing to that Region?

- A. That the Region has at least three Availability Zones
- B. That the required services, instance types, and features are available in that Region
- C. That the Region is closest to the company's head office
- D. That the Region uses the same pricing as us-east-1

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Regional service and instance availability varies, so a Region that satisfies compliance may still be unable to run the design.