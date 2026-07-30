# Chapter 22: Connecting Networks

---

Chapter 9 named the connectivity options. This chapter covers choosing between them and designing with them.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 22.1 VPC Peering

A direct network connection between two VPCs, routing over the AWS backbone rather than the internet.

- Works across accounts and across Regions.
- Traffic stays on the AWS network and is encrypted in transit for inter-Region peering.
- No bandwidth bottleneck and no single point of failure, since there is no gateway device.
- No charge for the connection itself; data transfer is charged, and cross-AZ or cross-Region rates apply.

**The three constraints that decide exam questions**

1. **Peering is not transitive.** If A peers with B and B peers with C, A cannot reach C. Each pair needs its own connection.
2. **CIDR blocks must not overlap.** There is no NAT between peered VPCs.
3. **Route tables must be updated on both sides.** Accepting a peering request does not create routes.

**Why it does not scale.** Connecting *n* VPCs in a full mesh requires *n(n-1)/2* connections. Five VPCs need ten. Ten VPCs need 45, each with route table entries on both sides. Past a handful of VPCs, Transit Gateway is the answer.

**Other limitations**: you cannot reference a security group in a peered VPC across Regions, and gateway VPC endpoints in one VPC cannot be used from a peered VPC.

---

## 22.2 AWS Transit Gateway

A regional hub connecting VPCs, VPN connections, Direct Connect gateways, and other Transit Gateways.

- **Hub and spoke replaces the mesh.** Each VPC attaches once. Adding the eleventh VPC is one attachment rather than ten peering connections.
- **Transitive routing works**, which is the point. Spokes can reach each other through the hub.
- **Transit Gateway route tables** control which attachments can reach which, so segmentation is a routing decision rather than a peering decision. A common pattern gives production and development separate route tables so they cannot reach each other, while both reach shared services.
- **Inter-Region peering** connects Transit Gateways in different Regions, with traffic staying on the AWS backbone and encrypted.
- **Shared across accounts** through AWS Resource Access Manager, so one networking account owns the gateway and application accounts attach to it.

**Cost.** An hourly charge per attachment plus a per-GB data processing charge. With few VPCs, peering is cheaper. The crossover typically arrives somewhere around five to ten VPCs, or earlier if transitive routing or centralized egress is required.

**Centralized egress** is the common Transit Gateway design: one egress VPC holds the NAT gateways, every spoke routes `0.0.0.0/0` to the Transit Gateway, and the gateway routes to the egress VPC. This replaces per-VPC NAT gateways with a shared pair, which usually saves more than the Transit Gateway costs.

**Transit Gateway or peering**

| Situation | Choose |
| --- | --- |
| Two VPCs, no growth expected | Peering |
| Lowest cost, no transitive requirement | Peering |
| Many VPCs, or growth expected | Transit Gateway |
| Spokes must reach each other | Transit Gateway |
| VPN or Direct Connect must reach many VPCs | Transit Gateway |
| Centralized egress or inspection | Transit Gateway |

---

## 22.3 AWS Site-to-Site VPN

An IPsec tunnel between a VPC and an on-premises network over the public internet.

- Each connection provides **two tunnels** to two endpoints in different Availability Zones, for redundancy on the AWS side. Configuring only one tunnel is a single point of failure and a common oversight.
- Terminates on a **virtual private gateway** attached to one VPC, or on a **Transit Gateway** to reach many.
- **Throughput is up to 1.25 Gbps per tunnel**, and a single tunnel does not aggregate. Higher throughput needs multiple connections with ECMP over Transit Gateway.
- Latency and jitter follow internet conditions and cannot be guaranteed.
- Charged per connection-hour plus data transfer out.

**Routing.** Static routing is simpler; BGP is preferred because it advertises routes automatically and fails over between tunnels without intervention. Questions mentioning automatic failover or dynamic route propagation want BGP.

**Where VPN fits.** Quick to establish, measured in hours rather than weeks. Suitable as a primary connection for modest bandwidth, and as a backup for Direct Connect.

---

## 22.4 AWS Direct Connect

A dedicated physical connection between an on-premises network and AWS through a Direct Connect location.

- **Consistent latency and bandwidth**, because traffic does not traverse the public internet.
- **Port speeds** from 50 Mbps to 100 Gbps depending on whether the connection is dedicated or hosted.
- **Lead time is weeks to months**, because physical cross-connects must be provisioned. Questions mentioning an immediate requirement are not Direct Connect questions on their own.
- **Not encrypted by itself.** It is private, which is not the same as encrypted. Where encryption is required, run a VPN over the Direct Connect, or use MACsec where the port supports it.

**Virtual interfaces**

| Type | Reaches |
| --- | --- |
| Private VIF | One VPC through a virtual private gateway |
| Transit VIF | A Direct Connect gateway attached to Transit Gateways, reaching many VPCs |
| Public VIF | AWS public endpoints such as S3 and DynamoDB, without traversing the internet |

**Direct Connect gateway** is a global resource letting one connection reach VPCs in multiple Regions, which a virtual private gateway alone cannot do.

**Resilience models**, which AWS documents explicitly and the exam asks about:

- **Development and test:** a single connection at a single location. No resilience.
- **High resilience:** two connections at two different Direct Connect locations. Survives a location failure.
- **Maximum resilience:** two connections at each of two locations, on separate devices. Survives device and location failure.

**The standard hybrid pattern** is Direct Connect as primary with a Site-to-Site VPN as backup, using BGP so failover is automatic. This gives predictable performance normally and continuity if the circuit fails, at far less cost than a second circuit.

---

## 22.5 AWS PrivateLink

Exposes a service privately to consumers in other VPCs and accounts, without peering, without route table changes, and without exposing the whole VPC.

**How it works.** The provider puts a Network Load Balancer or Gateway Load Balancer in front of the service and creates a **VPC endpoint service**. Consumers create an **interface endpoint**, which places an ENI with a private IP in their own subnet. Traffic flows one way, from consumer to service.

**Why it beats peering for this purpose**

- **CIDR overlap does not matter.** The consumer reaches a private IP in their own subnet, so the provider's addressing is irrelevant.
- **Only the service is exposed**, not the network. Peering opens routing between VPCs; PrivateLink opens one endpoint.
- **It scales to many consumers** without a connection per pair.
- **Connections are unidirectional**, which is usually what a service provider wants.

**Where it appears**

- SaaS vendors offering their service privately to customers in AWS.
- A shared services account exposing an internal API to many application accounts.
- Interface endpoints for AWS services, which are themselves built on PrivateLink.

**PrivateLink or peering.** If the requirement is "consumers need to reach one service," it is PrivateLink. If it is "these two networks need to route to each other," it is peering or Transit Gateway. Overlapping CIDRs in the question is a strong signal for PrivateLink.

---

## 22.6 Hybrid DNS

Connectivity without name resolution is half a solution, and DNS is a frequent gap in hybrid designs.

**Route 53 Resolver** is the DNS resolver present in every VPC at the base of the VPC CIDR plus two.

- **Inbound endpoints** let on-premises systems resolve AWS private hosted zone names. On-premises DNS forwards queries to the endpoint's IP addresses.
- **Outbound endpoints** and **resolver rules** let AWS resources resolve on-premises names, by forwarding queries for specified domains to on-premises DNS servers.

Both directions are usually needed, and each is a separate endpoint.

**Private hosted zones** resolve only within associated VPCs. A zone can be associated with VPCs in multiple accounts, which is how a central DNS account serves an organization.

**Resolver rules can be shared through AWS RAM**, so one networking account defines forwarding rules once and every account inherits them.

---

## 22.7 Choosing a Connectivity Option

| Requirement | Answer |
| --- | --- |
| Two VPCs must route to each other, few in number | VPC peering |
| Many VPCs must route to each other | Transit Gateway |
| On-premises to AWS, quickly, modest bandwidth | Site-to-Site VPN |
| On-premises to AWS, consistent latency, high bandwidth | Direct Connect |
| On-premises to AWS, private and encrypted | VPN over Direct Connect |
| Direct Connect with automatic failover | Direct Connect primary, VPN backup, with BGP |
| Consumers reach one service, CIDRs may overlap | PrivateLink |
| Reach S3 or DynamoDB privately from within a VPC | Gateway VPC endpoint |
| Reach S3 privately from on-premises | Interface endpoint, since gateway endpoints do not work over Direct Connect |
| One VPC's resources shared with other accounts | AWS RAM, from section 17.4 |
| On-premises must resolve AWS private DNS names | Route 53 Resolver inbound endpoint |
| AWS must resolve on-premises DNS names | Route 53 Resolver outbound endpoint with forwarding rules |

**A decision sequence**

1. Is this VPC to VPC, or on-premises to AWS?
2. If VPC to VPC: how many VPCs, and does anything need transitive routing? Few and no means peering; many or yes means Transit Gateway. Does only one service need exposing, or do the CIDRs overlap? Then PrivateLink.
3. If on-premises to AWS: what does the requirement say about consistency, bandwidth, and time to deliver? Consistent and high means Direct Connect. Fast to establish means VPN. Both usually means Direct Connect with VPN backup.
4. Whichever is chosen, confirm DNS resolution works in the direction needed.

---

## 22.8 End-of-Chapter Questions

**Q1.** VPC A is peered with VPC B, and VPC B is peered with VPC C. Instances in VPC A cannot reach VPC C. Why?

- A. The route tables in VPC B are misconfigured
- B. VPC peering is not transitive, so A and C require their own peering connection
- C. The CIDR blocks overlap
- D. Peering does not work across accounts

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Traffic cannot transit an intermediate VPC over peering; either peer A to C directly or replace the mesh with a Transit Gateway.

**Q2.** A company operates 25 VPCs across several accounts and needs them all to communicate, with segmentation between production and development. What should be used?

- A. VPC peering in a full mesh
- B. AWS Transit Gateway with separate route tables, shared through AWS RAM
- C. AWS PrivateLink between every pair
- D. Site-to-Site VPN between VPCs

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* A full mesh of 25 VPCs requires 300 peering connections; Transit Gateway route tables provide the segmentation and RAM provides cross-account sharing.

**Q3.** A SaaS provider must expose its application to customer VPCs, some of which use overlapping CIDR ranges with each other. Which service supports this?

- A. VPC peering
- B. AWS Transit Gateway
- C. AWS PrivateLink with a VPC endpoint service
- D. AWS Site-to-Site VPN

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* PrivateLink exposes only the service through an ENI in the consumer's subnet, so consumer addressing is irrelevant and overlap does not matter.

**Q4.** A financial services company requires consistent low latency to AWS and encryption of all traffic leaving its data center. What should be designed?

- A. Site-to-Site VPN only
- B. Direct Connect only
- C. Direct Connect with a Site-to-Site VPN running over it
- D. VPC peering to the on-premises network

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Direct Connect provides consistent latency but is private rather than encrypted, so a VPN over the top satisfies the encryption requirement.

**Q5.** On-premises servers connected over Direct Connect must access Amazon S3 privately, without traversing the internet. Which option works?

- A. A gateway VPC endpoint for S3
- B. An interface VPC endpoint for S3
- C. A NAT gateway in the VPC
- D. S3 Transfer Acceleration

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Gateway endpoints only function from within the VPC and cannot be reached over Direct Connect or VPN; interface endpoints can.

**Q6.** An application in AWS must resolve hostnames held in an on-premises DNS server. What should be configured?

- A. A Route 53 private hosted zone containing the records
- B. A Route 53 Resolver inbound endpoint
- C. A Route 53 Resolver outbound endpoint with forwarding rules for the on-premises domain
- D. A public hosted zone with the on-premises records

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Outbound endpoints forward queries from AWS to on-premises resolvers; inbound endpoints handle the opposite direction.
