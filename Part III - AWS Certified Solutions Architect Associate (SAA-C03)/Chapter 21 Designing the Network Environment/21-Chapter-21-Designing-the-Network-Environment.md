# Chapter 21: Designing the Network Environment

---

Chapter 9 covered what VPCs, subnets, route tables, security groups, and network ACLs are. This chapter covers laying them out. The component definitions and the security group versus network ACL comparison are in Chapter 9 and are not repeated.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 21.1 VPC Sizing and Subnet Design

**Choosing the VPC CIDR**

- Permitted sizes are `/16` through `/28`. Use a `/16` unless there is a reason not to; unused address space costs nothing.
- Use RFC 1918 private ranges: `10.0.0.0/8`, `172.16.0.0/12`, or `192.168.0.0/16`.
- **Do not overlap with anything you might ever connect to.** Overlapping CIDRs make VPC peering impossible and Transit Gateway routing painful. This includes on-premises ranges, other accounts' VPCs, and any company you might acquire.
- Allocate ranges centrally. A common scheme gives each environment and Region a distinct `/16` from a planned block, recorded somewhere authoritative. AWS **IP Address Manager (IPAM)** automates this across an organization.

**Sizing subnets**

Remember that AWS reserves five addresses per subnet, so a `/28` yields 11 usable addresses and a `/24` yields 251.

| Subnet size | Usable | Suits |
| --- | --- | --- |
| /28 | 11 | Very small, such as a NAT or endpoint subnet |
| /24 | 251 | A typical application tier |
| /22 | 1,019 | A large Auto Scaling group |
| /20 | 4,091 | EKS clusters, where every pod may consume an address |

**Size for the largest thing that will ever run there.** The common failure is an EKS cluster in a `/24`: with the VPC CNI plugin assigning pod addresses from the subnet, a few dozen nodes exhaust it, and the cluster stops scheduling pods for a reason that looks nothing like a networking problem.

**Growing a VPC.** The primary CIDR cannot be resized. Up to four secondary CIDR blocks can be added, which is the escape route, but they must not overlap and routing becomes harder to reason about. Sizing correctly the first time is cheaper.

---

## 21.2 The Three-Tier Subnet Layout

The standard layout, repeated across at least two Availability Zones:

| Tier | Route to `0.0.0.0/0` | Contains |
| --- | --- | --- |
| Public | Internet gateway | Load balancers, NAT gateways, bastion hosts |
| Private | NAT gateway | Application servers, containers, Lambda ENIs |
| Isolated | No default route | Databases, and anything that must never reach the internet |

**Why three rather than two.** A database in a private subnet with a NAT route can reach the internet outbound. That is usually unnecessary and occasionally how data leaves. An isolated tier with no default route removes the possibility, and databases needing patches get them through the managed service rather than through egress.

**Availability Zone symmetry.** Create the same tiers in each zone with equally sized CIDRs. Asymmetric subnets cause capacity to be unavailable in one zone during a failure in another, which is the situation multi-AZ was meant to prevent.

**Route table design.** One route table per tier per Availability Zone where NAT gateways are zonal, because each private subnet must route to the NAT gateway in its own zone. A single shared private route table pointing at one NAT gateway creates a cross-zone dependency and a single point of failure.

**Worked layout for a `10.0.0.0/16` VPC across two zones**

| Subnet | CIDR | Zone | Tier |
| --- | --- | --- | --- |
| public-a | 10.0.0.0/24 | a | Public |
| public-b | 10.0.1.0/24 | b | Public |
| app-a | 10.0.16.0/20 | a | Private |
| app-b | 10.0.32.0/20 | b | Private |
| data-a | 10.0.48.0/24 | a | Isolated |
| data-b | 10.0.49.0/24 | b | Isolated |

Note the application tiers are `/20` and the rest are `/24`. Size by what the tier holds, not uniformly.

---

## 21.3 NAT Design

A NAT gateway allows outbound-initiated connections from private subnets while providing no inbound path.

**Costs, which drive most NAT decisions**

- An hourly charge per gateway, whether or not traffic flows.
- A per-GB data processing charge on everything passing through.

A NAT gateway is often among the largest line items in a small account, and it is the resource most commonly left running after a lab.

**Availability versus cost**

- **One NAT gateway per Availability Zone** is the resilient design. Each private subnet routes to the gateway in its own zone, so a zone failure does not remove egress for the others.
- **One NAT gateway shared across zones** is cheaper by the hourly rate but creates a cross-zone dependency, adds cross-AZ data transfer charges, and means a zone failure removes egress for every zone.

For production, one per zone. For development, one shared is a defensible trade-off if the consequences are understood.

**Reducing NAT cost**

1. **VPC endpoints for AWS services.** Traffic to S3 and DynamoDB through a gateway endpoint bypasses NAT entirely and is free. This is frequently the single largest saving available.
2. **Interface endpoints** for other AWS services, which cost less per GB than NAT processing for high-volume traffic.
3. **Question whether egress is needed at all.** Instances launched from a golden image with dependencies baked in may not need outbound internet, in which case the tier becomes isolated and the NAT gateway disappears.

**NAT instance** is the legacy alternative: a self-managed EC2 instance performing NAT. It is cheaper at small scale and is your responsibility to patch, scale, and make highly available. AWS recommends NAT gateway. It appears in exam questions mainly as a distractor, though it is a legitimate answer when a question stresses minimizing cost in a non-production environment.

---

## 21.4 VPC Endpoints

Endpoints keep traffic to AWS services on the AWS network rather than routing it over the internet.

| | Gateway endpoint | Interface endpoint |
| --- | --- | --- |
| Services | Amazon S3 and DynamoDB only | Most AWS services, and PrivateLink partner services |
| Mechanism | A route table entry with a prefix list target | An elastic network interface with a private IP in your subnet |
| Cost | None | Hourly per endpoint per AZ, plus per GB |
| DNS | Requires prefix list routing; the public endpoint name resolves normally | Private DNS makes the standard service name resolve to the private IP |
| Cross-VPC | Cannot be used from a peered VPC or over VPN | Can be reached from peered VPCs, VPN, and Direct Connect |
| Access control | Endpoint policy | Endpoint policy and security group |

**Design guidance**

- Create gateway endpoints for S3 and DynamoDB in every VPC. They are free and remove NAT charges for that traffic.
- Create interface endpoints where traffic volume justifies the hourly cost, or where a compliance requirement says AWS API traffic must not traverse the internet. Common candidates are Systems Manager, Secrets Manager, KMS, ECR, and CloudWatch Logs.
- **Endpoint policies** restrict which resources can be reached through the endpoint, which is how you enforce that instances may only reach your own buckets and not arbitrary public ones.
- Deploy interface endpoints in at least two Availability Zones. An endpoint ENI is zonal.

**A note on S3 gateway endpoints and on-premises.** They only work from within the VPC. If on-premises systems need private S3 access over Direct Connect, an interface endpoint is required.

---

## 21.5 Layered Network Security

Controls belong at different layers, and using the wrong one is a common design error.

| Layer | Control | Handles |
| --- | --- | --- |
| Edge | AWS Shield, AWS WAF on CloudFront | Volumetric DDoS, application-layer attacks before traffic reaches the Region |
| Regional edge | AWS WAF on ALB or API Gateway | SQL injection, cross-site scripting, rate limiting, geographic blocking |
| Subnet | Network ACL | Coarse allow and deny, blocking a specific address range |
| Instance | Security group | The primary access control, referencing other security groups |
| VPC | AWS Network Firewall | Stateful inspection, domain filtering, intrusion prevention across the whole VPC |
| Organization | AWS Firewall Manager | Applying WAF rules, Shield protections, and security group policies centrally |

**How to choose**

- **Security groups are the primary control.** Reference other security groups rather than CIDR ranges wherever the source is an AWS resource.
- **Network ACLs are for explicit denies**, which security groups cannot express, and for subnet-wide blanket rules. Trying to manage fine-grained access with them produces rules nobody can reason about.
- **AWS WAF** operates on HTTP content, so it cannot protect a database port. If the question is about SQL injection or bot traffic against a web application, it is WAF. If it is about which instances may talk to which, it is security groups.
- **AWS Network Firewall** handles what neither can: outbound domain filtering, deep packet inspection, and intrusion prevention. It is the answer when a question describes needing to restrict which external domains instances may reach.
- **Shield Standard** is automatic and free. **Shield Advanced** adds the response team, cost protection against DDoS-driven scaling, and WAF at no extra charge on protected resources.

**Egress filtering.** Most designs control inbound carefully and allow all outbound, which is the default. Where data exfiltration is a stated concern, restrict egress: security group outbound rules, endpoint policies, and Network Firewall domain lists, in that order of increasing capability and cost.

---

## 21.6 IPv6 and Dual-Stack Design

- A VPC can be dual-stack, carrying both IPv4 and IPv6. It cannot be IPv6-only, though individual subnets can be.
- AWS assigns the IPv6 CIDR from its own pool by default, as a `/56`, with subnets receiving a `/64`. Bring-your-own IPv6 is supported.
- **IPv6 addresses are public and globally routable.** There is no NAT in IPv6, so an instance with an IPv6 address and a route to an internet gateway is reachable from the internet unless security groups say otherwise.
- **Egress-only internet gateway** is the IPv6 equivalent of a NAT gateway: outbound-initiated traffic only, inbound blocked. It carries no hourly charge, which is one practical argument for IPv6.
- Security group and network ACL rules are per address family. A rule allowing HTTP from `0.0.0.0/0` does not allow it from `::/0`, and forgetting the second rule causes IPv6 clients to fail while IPv4 clients succeed.

**When it appears on the exam.** Usually as the answer to running out of private IPv4 address space, or to a requirement that the workload support IPv6 clients. It is also the answer when a question asks for outbound-only internet access for IPv6 without a NAT gateway.

---

## 21.7 Network Observability

**VPC Flow Logs** record accepted and rejected traffic metadata at VPC, subnet, or interface level, delivered to CloudWatch Logs, S3, or Data Firehose. They record the fact of a connection, not its contents. Use them to confirm whether traffic arrived at all, which distinguishes a routing problem from an application problem.

**Traffic Mirroring** copies actual packets from an ENI to a monitoring appliance, for intrusion detection and deep inspection. It is heavier than flow logs and used where content matters.

**Reachability Analyzer** statically analyzes the configuration between two resources and reports whether a path exists and, if not, which component blocks it. It is the fastest way to answer "why can this instance not reach that database" without touching the traffic.

**Network Access Analyzer** works the other direction: it identifies unintended paths, such as anything that can reach the internet when it should not.

**A troubleshooting order that works**

1. Reachability Analyzer, to find a configuration break.
2. Flow logs filtered to the source and destination, to see whether packets arrive and whether they are rejected.
3. Security group, then network ACL, then route table, then the application.

Rejections in flow logs with no matching security group rule point to the security group. Rejections with a matching rule usually point to a network ACL missing the ephemeral port range on the return path, since network ACLs are stateless.

---

## 21.8 End-of-Chapter Questions

**Q1.** An architect is planning a VPC that will later connect to an on-premises network over Direct Connect. Which consideration is most important when choosing the CIDR block?

- A. Selecting the largest permitted block, a /16
- B. Ensuring the range does not overlap with the on-premises network or other connected VPCs
- C. Using only the 10.0.0.0/8 range
- D. Reserving five addresses per subnet

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Overlapping ranges make peering impossible and routing to on-premises unworkable, and the CIDR cannot be changed after creation.

**Q2.** Instances in private subnets download large volumes of data from Amazon S3, and NAT gateway data processing charges dominate the bill. What is the most effective change?

- A. Move the instances to public subnets with Elastic IP addresses
- B. Create a gateway VPC endpoint for S3 and route the traffic through it
- C. Replace the NAT gateway with a NAT instance
- D. Enable S3 Transfer Acceleration

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Gateway endpoints for S3 carry no hourly or per-GB charge and remove that traffic from the NAT path entirely.

**Q3.** A security requirement states that EC2 instances must only be able to reach a specific list of external domains. Which service enforces this?

- A. Security groups
- B. Network ACLs
- C. AWS Network Firewall with domain list rules
- D. AWS WAF

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Security groups and network ACLs filter by address and port, not domain name, and WAF inspects inbound HTTP rather than outbound destinations.

**Q4.** An application in a dual-stack VPC is reachable over IPv4 but IPv6 clients time out, despite the load balancer having IPv6 addresses. What is the most likely cause?

- A. Egress-only internet gateway is missing
- B. The security group allows HTTP from 0.0.0.0/0 but has no equivalent rule for ::/0
- C. IPv6 is not supported by Application Load Balancers
- D. The subnet lacks an IPv6 CIDR block

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Security group rules are per address family, so an IPv4 rule does not permit IPv6 traffic.

**Q5.** An EC2 instance cannot connect to an RDS database in the same VPC. Which tool identifies which configuration component is blocking the path, without generating traffic?

- A. VPC Flow Logs
- B. Traffic Mirroring
- C. VPC Reachability Analyzer
- D. AWS X-Ray

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Reachability Analyzer statically evaluates the configuration and names the blocking component; flow logs require traffic to have been attempted.

**Q6.** A design places application servers in private subnets and databases in subnets with no route to a NAT gateway or internet gateway. What does the isolated tier primarily achieve?

- A. Lower data transfer costs between tiers
- B. Removal of any outbound path to the internet from the data tier
- C. Automatic encryption of database traffic
- D. Higher availability for the database

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Without a default route the data tier cannot initiate outbound internet connections, which closes an exfiltration path that a private subnet with NAT leaves open.
