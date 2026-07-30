# Chapter 29: Migration and Disaster Recovery

---

Two subjects sit together here because both are about moving workloads under constraint: migration moves them once, deliberately; disaster recovery moves them again, under pressure. Both are planned the same way, by working backward from a target.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 29.1 Migration Strategies

AWS describes seven approaches, commonly called the seven Rs. The decision is made per application, not per estate.

| Strategy | What it means | Choose when |
| --- | --- | --- |
| Rehost | Lift and shift with no change | Speed matters, or the application cannot be modified now |
| Replatform | Lift and reshape, such as moving a self-managed database to RDS | A contained change removes significant operational burden |
| Repurchase | Replace with a SaaS product | The capability is not a differentiator |
| Refactor | Re-architect for cloud-native services | Business need justifies the cost, usually for scaling or agility |
| Relocate | Move infrastructure wholesale without changing anything, such as VMware workloads | A hypervisor-level move is available |
| Retain | Leave it where it is, for now | Recent investment, a hard dependency, or imminent retirement |
| Retire | Switch it off | Nobody uses it |

**Retire is the most valuable and most overlooked.** Portfolio discovery routinely finds that 10% to 20% of servers serve nothing. Migrating them costs money twice: once to move, then forever to run.

**Rehost first, then improve.** Refactoring during migration means two hard changes at once, and when something breaks nobody knows which caused it. Rehost or replatform to get out of the data center, then modernize with the estate stable and the rollback path already gone.

**Replatform is usually the best value.** Moving a self-managed MySQL server to Amazon RDS is a small change that removes patching, backups, and failover work permanently.

---

## 29.2 Discovery and Planning

**AWS Application Discovery Service** collects server configuration, utilization, and network dependency data from on-premises environments, either agentless through a VMware appliance or agent-based per server.

**AWS Migration Hub** aggregates discovery data and tracks migration progress across tools and accounts in one view.

**What discovery must produce**

- A full inventory, including the servers nobody remembers.
- Dependency mapping, which determines what must move together. Migrating an application without its database, or without a service it calls over a low-latency link, is how migrations fail on cutover night.
- Utilization data, so the target is right-sized rather than a copy of oversized hardware.
- Business criticality, which sets the order.

**Migration waves.** Group applications by dependency, so everything in a wave moves together, and sequence waves from lowest to highest risk. The first wave should be something whose failure would be tolerable, because the first wave is where the process is learned.

**AWS Transform** and the AWS Migration Acceleration Program provide tooling and funding for large migrations, and appear in questions about organizational-scale programs rather than individual workloads.

---

## 29.3 Migration Execution

**Servers: AWS Application Migration Service (MGN).** Continuous block-level replication from source servers into a staging area in AWS, with cutover when ready. Because replication runs continuously, cutover downtime is minutes rather than the length of a full copy. This is the default answer for rehosting.

**Databases: AWS Database Migration Service (DMS).**

- **Homogeneous migration**, such as Oracle to Oracle or MySQL to MySQL, is a direct move.
- **Heterogeneous migration**, such as Oracle to Aurora PostgreSQL, requires the **AWS Schema Conversion Tool** first to convert schema, stored procedures, and functions. SCT reports what it could not convert automatically, and that report is the real project plan.
- **Change data capture** replicates ongoing changes after the initial load, so the source stays live until cutover and downtime is minimal. Any question mentioning minimal downtime for a database migration wants CDC.

**Bulk data**

| Situation | Service |
| --- | --- |
| Ongoing or scheduled transfer over the network | AWS DataSync |
| One-off transfer where the network can carry it in time | AWS DataSync |
| Data volume the network cannot carry in the available time | AWS Snow Family, subject to the availability limits in section 1.6.6 |
| Existing SFTP or FTPS workflows must keep working | AWS Transfer Family |
| On-premises systems need continuing access to AWS storage | AWS Storage Gateway |

**The arithmetic that decides it.** Divide the data volume by the usable bandwidth. 100 TB over a 1 Gbps link, at realistic utilization, takes over a week of saturated network. If that exceeds the window or the link is needed for anything else, physical transfer wins.

**VMware workloads.** Amazon Elastic VMware Service runs VMware Cloud Foundation inside your VPC, which supports the Relocate strategy. As noted in section 10.1, AWS stopped reselling VMware Cloud on AWS on April 30, 2024.

---

## 29.4 RTO and RPO

Every disaster recovery decision follows from two numbers, and they must come from the business rather than from the architect.

- **Recovery Time Objective (RTO)** is the maximum acceptable time to restore service.
- **Recovery Point Objective (RPO)** is the maximum acceptable data loss, expressed as time.

```
        <-- RPO -->  incident  <-- RTO -->
   last recoverable state   |    service restored
```

**Ask the question in business terms.** "How long can we be down before it becomes a serious problem?" and "How much recent work can we afford to lose?" produce more useful answers than asking for an RTO. The follow-up matters too: what does each hour of downtime cost, because that number is what justifies the spend.

**Set them per workload.** A single organization-wide RTO either overspends on things that do not matter or underspends on things that do. The payments system and the internal wiki do not need the same design.

---

## 29.5 The Four DR Strategies

| Strategy | RTO | RPO | Standing cost | What runs in the recovery Region |
| --- | --- | --- | --- | --- |
| Backup and restore | Hours to a day | Hours | Lowest | Nothing but backups |
| Pilot light | Tens of minutes to hours | Minutes | Low | Data replicated, core services off |
| Warm standby | Minutes | Seconds to minutes | Medium | A scaled-down but running copy |
| Multi-site active/active | Near zero | Near zero | Highest | A full copy serving traffic |

**Backup and restore.** Back up data and configuration, restore into the recovery Region when needed. Cheapest and slowest. Adequate for workloads that can be down for hours. Make backups cross-Region and cross-account, and use infrastructure as code so the environment is redeployed rather than rebuilt by hand.

**Pilot light.** Data replicates continuously and the minimal core, typically the database, exists in the recovery Region. Application servers are defined but not running. Recovery means starting compute and scaling up. Cheaper than warm standby because compute is off; slower because it must start.

**Warm standby.** A complete but scaled-down environment runs continuously in the recovery Region, capable of handling a fraction of production traffic. Recovery means scaling up and redirecting traffic. Faster than pilot light because everything is already running and proven.

**Multi-site active/active.** Both Regions serve production traffic. Failover is a routing change. This gives the best RTO and RPO and is the most expensive and complex, requiring data consistency across Regions, which is a genuinely hard problem when both sides accept writes.

**The relationship worth stating.** Cost rises with recovery speed, roughly in proportion. The design question is not which is best but which the business is willing to fund given what an outage costs.

---

## 29.6 Backup Design

**AWS Backup** centralizes policy across EBS, EFS, FSx, RDS, Aurora, DynamoDB, S3, Storage Gateway, and more.

- **Backup plans** define frequency, backup window, lifecycle to cold storage, and retention.
- **Resource assignment** selects what a plan protects, by tag or by resource, so new resources with the right tag are protected automatically.
- **Backup vaults** hold recovery points and are encrypted with a KMS key.
- **Vault Lock** makes retention immutable, so backups cannot be deleted early even by an administrator. This is the control that survives a compromised account.
- **Cross-Region and cross-account copy** run as part of the plan.

**The 3-2-1 principle applied to AWS.** Three copies, on two kinds of storage, one off-site. In AWS terms: the production data, a backup in the same Region, and a copy in another Region or account. Same-account backups do not protect against account compromise, which is why cross-account copies matter.

**Backups are worthless until restored.** Test restores on a schedule and record how long they take, because that measurement is the real RTO. An untested backup is an assumption.

---

## 29.7 Replication Choices for DR

| Data | Mechanism | Typical RPO |
| --- | --- | --- |
| S3 objects | Cross-Region Replication, with Replication Time Control for a 15-minute SLA | Minutes |
| RDS | Cross-Region read replica, or automated backup replication | Seconds to minutes |
| Aurora | Aurora Global Database | Typically under a second |
| DynamoDB | Global tables | Sub-second |
| EBS | Snapshots copied cross-Region, or AWS Elastic Disaster Recovery | Minutes to hours |
| EFS | AWS Backup cross-Region copy, or EFS replication | Minutes |
| Whole servers | AWS Elastic Disaster Recovery (DRS) | Sub-second, continuous block replication |

**AWS Elastic Disaster Recovery** continuously replicates servers into a low-cost staging area and launches them on demand, which delivers pilot-light economics with warm-standby recovery times. It is the standard answer for disaster recovery of servers, including from on-premises.

**Aurora Global Database** gives a secondary Region typically under a second behind, with promotion in about a minute. It is the answer for a relational RPO measured in seconds.

**DynamoDB global tables** are multi-active, so both Regions accept writes, with last-writer-wins conflict resolution. That resolution model must be acceptable to the application.

**What replication does not solve.** Replication propagates corruption and deletion faithfully and immediately. A dropped table appears in the secondary Region instantly. Point-in-time recovery and immutable backups are the defense against logical failure; replication is the defense against infrastructure failure. A complete design needs both.

---

## 29.8 Testing and Runbooks

**A runbook** states, in order: how failure is detected, who decides to fail over, the exact steps, how to verify service is restored, and how to fail back. Written before the incident, and specific enough that someone unfamiliar with the system can follow it at 3am.

**Testing levels**

1. **Tabletop.** Walk through the runbook without touching anything. Finds missing steps and unclear ownership cheaply.
2. **Component failover.** Fail over one element, such as a database, in a non-production environment.
3. **Full failover.** Fail the workload into the recovery Region and serve real traffic from it. This is the only test that proves the design.
4. **Game days.** Deliberately inject failure and let the team respond, which tests the people and process as much as the architecture.

**AWS Fault Injection Service** runs controlled experiments, such as terminating instances, injecting latency, or simulating an Availability Zone interruption, with stop conditions tied to CloudWatch alarms so an experiment halts before causing real harm.

**AWS Resilience Hub** assesses a workload against defined RTO and RPO targets, identifies gaps, and tracks resilience over time.

**Fail back too.** Most plans cover failing over and not returning. Failing back is often harder, because the recovery Region has accumulated writes that must be reconciled with the primary.

**Test on a schedule.** A plan tested once at design time and never again is documentation, not a capability. Configuration drifts, dependencies change, and the people who wrote it leave.

---

## 29.9 End-of-Chapter Questions

**Q1.** A company must migrate an Oracle database to Amazon Aurora PostgreSQL with minimal downtime. Which combination of services is required?

- A. AWS DataSync and AWS Backup
- B. AWS Schema Conversion Tool to convert the schema, then AWS DMS with change data capture
- C. AWS DMS alone
- D. AWS Application Migration Service

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* A heterogeneous migration needs schema conversion first, and change data capture keeps the target current so cutover downtime is minimal.

**Q2.** A workload can tolerate up to four hours of downtime and one hour of data loss, and the business wants the lowest standing cost that meets those targets. Which DR strategy fits?

- A. Backup and restore
- B. Pilot light
- C. Warm standby
- D. Multi-site active/active

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Pilot light keeps data replicated with compute off, meeting an RTO in the tens of minutes to hours and an RPO in minutes at far lower cost than warm standby.

**Q3.** An organization must transfer 500 TB to AWS within two weeks over a 1 Gbps internet connection that is also used for production traffic. What should be used?

- A. AWS DataSync over the existing connection
- B. AWS Transfer Family
- C. Physical transfer using the AWS Snow Family
- D. S3 Transfer Acceleration

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* 500 TB over a shared 1 Gbps link would take far longer than two weeks even at full saturation, so physical transfer is the only option that meets the deadline.

**Q4.** A team relies on Cross-Region Replication for disaster recovery. An engineer accidentally deletes a large number of objects, and the deletions appear in the secondary Region. What was missing from the design?

- A. Replication Time Control
- B. Versioning with lifecycle rules, and immutable backups, since replication propagates deletions rather than protecting against them
- C. A second replication rule
- D. Cross-account replication

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Replication defends against infrastructure failure, not logical failure; versioning, point-in-time recovery, and immutable backups defend against accidental or malicious deletion.

**Q5.** A relational workload requires a cross-Region recovery point objective measured in seconds and promotion within about a minute. Which option meets this?

- A. Automated backups copied cross-Region
- B. A cross-Region read replica on Amazon RDS
- C. Amazon Aurora Global Database
- D. AWS Backup with a cross-Region copy

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Aurora Global Database replicates with typical lag under a second and promotes a secondary Region in around a minute, which backups and standard read replicas cannot match.

**Q6.** During a portfolio assessment, discovery finds that 15% of servers have no active users or dependencies. What is the correct migration strategy for them?

- A. Rehost, to move quickly
- B. Replatform, to reduce operational overhead
- C. Retain, pending further analysis
- D. Retire

**Answer: D.** *Target exam: AWS Certified Solutions Architect - Associate.* Servers serving nothing should be decommissioned rather than migrated, since migrating them costs money once to move and indefinitely to run.
