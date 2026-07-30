# Chapter 20: Designing the Database Layer

---

Chapter 12 covered what each database service is. This chapter covers selecting between them and designing what you select. The engine lists, service descriptions, and the Multi-AZ versus read replica comparison are in Chapter 12 and are not repeated.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 20.1 Selecting a Database Service

Work through these in order. The first question that gives a clear answer usually decides it.

1. **Does the data have relationships that queries traverse, and does the application join across tables?** If yes, relational. If no, keep going.
2. **Does the workload need multi-row ACID transactions?** If yes, relational. DynamoDB has transactions, but they are constrained and expensive at scale.
3. **What is the access pattern?** Known keys at high volume points to DynamoDB. Ad hoc queries across many dimensions points to relational or a warehouse.
4. **What latency is required?** Microseconds means in-memory. Single-digit milliseconds means DynamoDB or a cached relational tier.
5. **How large will it get, and in which direction?** Vertical growth suits relational. Unbounded horizontal growth suits DynamoDB.

| Requirement in the question | Service |
| --- | --- |
| Managed relational, existing engine, minimal change | Amazon RDS |
| Relational at high throughput, MySQL or PostgreSQL compatible, low operational overhead | Amazon Aurora |
| Relational with unpredictable or intermittent load | Aurora Serverless v2 |
| Key-value or document at any scale, single-digit millisecond, serverless | Amazon DynamoDB |
| Microsecond reads of frequently accessed data | Amazon ElastiCache, or DAX in front of DynamoDB |
| In-memory as the primary store, with durability | Amazon MemoryDB |
| Analytical queries over billions of rows | Amazon Redshift |
| Relationships are the query: fraud rings, recommendations, social graphs | Amazon Neptune |
| MongoDB-compatible document workload | Amazon DocumentDB |
| Cassandra-compatible wide column | Amazon Keyspaces |
| Time-stamped measurements at high ingest | Amazon Timestream |
| Full-text search and log analytics | Amazon OpenSearch Service |
| An engine or OS-level configuration RDS does not support | Self-managed on EC2 |

**Signals that point to self-managed on EC2**, which is otherwise the wrong answer: a database engine RDS does not offer, a version RDS has retired, an operating system level agent the application requires, or superuser access to the database host. If none of those appear, RDS or Aurora is preferred.

**Aurora or RDS.** Aurora costs more per instance-hour but usually less in total, because its storage is consumption-based, it scales storage automatically, replicas share one storage volume rather than replicating independently, and failover is faster. Choose plain RDS when the engine is not MySQL or PostgreSQL, when the workload is small enough that Aurora's minimum footprint is not justified, or when cost predictability matters more than performance.

---

## 20.2 Designing for RDS Availability

**Multi-AZ DB instance deployment.** One standby in another Availability Zone, synchronously replicated, not serving traffic. Failover is automatic and typically completes in one to two minutes, by repointing the DNS endpoint. The application reconnects; it does not need to know a failover happened, provided it handles connection loss.

**Multi-AZ DB cluster deployment.** One writer and two readable standbys across three Availability Zones. Failover is typically under 35 seconds, and the standbys serve read traffic, unlike the instance deployment. Available for MySQL and PostgreSQL. This is the answer when a question wants both faster failover and readable standbys.

**Aurora availability.** Storage is replicated six ways across three Availability Zones and self-heals. A cluster can have up to 15 replicas, any of which can be promoted, typically in under 30 seconds. Failover priority tiers determine which replica is chosen.

**Designing the application side**

- Always connect through the **endpoint**, never an IP address. Failover changes which instance the endpoint resolves to.
- Set a short DNS TTL and ensure the client honors it. Java clients caching DNS indefinitely are a classic cause of failover taking far longer than the database did.
- Implement connection retry with backoff. A failover is a brief connection failure by definition.
- Aurora exposes a **cluster endpoint** for writes, a **reader endpoint** that load-balances across replicas, and **custom endpoints** for directing particular workloads at particular instances.

**What Multi-AZ does not do.** It does not protect against a Regional failure, and it does not protect against logical corruption. A dropped table replicates to the standby instantly. Recovery from that is point-in-time restore, covered in section 20.7.

---

## 20.3 Scaling Reads and Writes

**Reads**

- **Read replicas** on RDS, up to 15 for most engines, asynchronous, promotable manually. They can be cross-Region, which also serves disaster recovery and latency reduction.
- **Aurora replicas** share the cluster storage volume, so replication lag is typically measured in tens of milliseconds rather than seconds, and adding one does not load the writer.
- **Aurora Auto Scaling** adds and removes replicas based on connections or CPU.
- **Caching** removes reads before they reach the database at all, covered in section 20.5.

The design order matters: cache first, then add replicas. A read replica costs a full instance and inherits the same query; a cache hit costs almost nothing.

**Writes**

Write scaling is genuinely harder, because a single writer is a hard ceiling on relational engines.

- **Scale vertically** to a larger instance, which is the simplest answer and has a ceiling.
- **Offload work** by moving analytics, reporting, and search to Redshift, Athena, or OpenSearch rather than running them against the transactional database.
- **Batch and buffer** writes through SQS or Kinesis so bursts do not hit the database directly.
- **Shard** across multiple databases by a partition key. This is application work, not a database feature, and it removes cross-shard joins and transactions. It is a last resort on relational.
- **Move the workload** to DynamoDB, which scales writes horizontally by design.

When a question describes write throughput that a single instance cannot sustain and the data model does not require joins, the intended answer is usually DynamoDB rather than a larger RDS instance.

---

## 20.4 DynamoDB Data Modeling

DynamoDB rewards designing the table around the queries, which is the opposite of relational normalization.

**Primary key**

- **Partition key alone** gives a simple key; every item must have a unique value.
- **Partition key plus sort key** gives a composite key, allowing many items per partition and range queries within it.

The partition key determines physical distribution, so it is the most consequential decision in the design.

**Hot partitions.** A partition key with few distinct values, or one where a small number of values receive most traffic, concentrates load and throttles. Fixes:

- Choose a higher-cardinality attribute.
- Add a suffix to spread writes, for example appending a random number to a date-based key.
- Use **adaptive capacity**, which DynamoDB applies automatically and mitigates but does not eliminate the problem.

**Secondary indexes**

| | Global secondary index | Local secondary index |
| --- | --- | --- |
| Partition key | Different from the table | Same as the table |
| Sort key | Any attribute | Different from the table |
| Created | Any time | Only at table creation |
| Capacity | Its own | Shares the table's |
| Consistency | Eventually consistent only | Supports strongly consistent reads |
| Size limit | None | 10 GB per partition key value |

GSIs are the general answer. LSIs are worth their constraints only when strongly consistent reads on an alternate sort key are required.

**Capacity mode**

- **On-demand** scales instantly and bills per request. Correct for unpredictable, spiky, or new workloads, and for anything where idle periods are long.
- **Provisioned** reserves read and write capacity units and is significantly cheaper for steady, predictable traffic. Combine with Auto Scaling to follow a daily pattern.

**Other design points**

- **Item size limit is 400 KB.** Larger payloads go in S3 with a pointer stored in the item.
- **Query is efficient; Scan is not.** A Scan reads every item. If a design requires scans, the key schema is wrong or an index is missing.
- **DynamoDB Streams** emit an ordered change record per item, retained 24 hours, commonly consumed by Lambda for aggregation, replication, or event publication.
- **Global tables** give multi-active replication across Regions, with last-writer-wins conflict resolution. They require streams and add cross-Region write cost.
- **TTL** expires items automatically at a timestamp you set, at no cost, which is the correct way to age out session data.

---

## 20.5 Caching the Data Tier

**ElastiCache engines**

| | Redis (and Valkey) | Memcached |
| --- | --- | --- |
| Data structures | Lists, sets, sorted sets, hashes, streams | Strings only |
| Persistence | Optional snapshots and append-only file | None |
| Replication and failover | Yes, with Multi-AZ | No |
| Scaling | Read replicas and cluster mode sharding | Horizontal by adding nodes |
| Use | Sessions, leaderboards, pub/sub, anything needing durability or failover | Simple, large, sharded object cache |

Redis is the default choice. Memcached is appropriate only for a simple cache where losing the whole contents is acceptable and multithreaded performance on large nodes matters.

**Caching strategies**

- **Lazy loading**, or cache-aside. Read from cache; on a miss, read the database and populate the cache. Only requested data is cached, and a miss costs an extra round trip. Stale data persists until eviction or expiry.
- **Write-through.** Write to cache and database together. Cached data is never stale, but every write pays cache cost and data that is never read is still cached.
- **TTL on everything.** Whichever strategy is used, an expiry bounds how stale data can become. Combining lazy loading with a TTL is the common production pattern.

**DynamoDB Accelerator (DAX)** is an in-memory cache specifically for DynamoDB, delivering microsecond reads through the same API, so the application needs no cache-handling code. It caches eventually consistent reads only. If a question mentions microsecond latency on DynamoDB, DAX is the answer; if it mentions strongly consistent reads, DAX does not help.

---

## 20.6 Database Security

**Network.** Place databases in private subnets with no route to an internet gateway. Control access with a security group whose inbound rule references the application tier's security group rather than a CIDR range. Set **Public access** to No on RDS, and use a DB subnet group spanning at least two Availability Zones.

**Authentication**

- **IAM database authentication** for RDS MySQL and PostgreSQL and for Aurora issues a short-lived token instead of a password, removing stored credentials entirely. Its constraint is a connection rate limit, which makes it unsuitable for very high connection churn.
- **Secrets Manager** stores and rotates credentials, with built-in rotation for RDS, Aurora, Redshift, and DocumentDB. This is the standard answer where a password must exist.
- **Kerberos through AWS Managed Microsoft AD** for engines supporting it.

**Encryption**

- At rest, enabled at creation with KMS. **It cannot be added to an existing unencrypted RDS instance.** The path is to snapshot, copy the snapshot with encryption enabled, and restore from the encrypted copy. This is a frequent exam scenario.
- In transit, enforced by requiring SSL or TLS in the parameter group, for example `rds.force_ssl` on PostgreSQL.
- Encrypting the primary encrypts its replicas, snapshots, and automated backups.

**Auditing.** Enable the engine's audit log and export it to CloudWatch Logs. **Database Activity Streams** on Aurora provide a near real-time stream of database activity that database administrators cannot alter, which is what separation-of-duties requirements ask for.

---

## 20.7 Backup and Recovery for Databases

**Automated backups** run daily during a backup window and retain transaction logs, enabling point-in-time recovery to any second within the retention period, which is 1 to 35 days. Setting retention to 0 disables them, which also disables PITR.

**Manual snapshots** persist until explicitly deleted and survive deletion of the instance. Automated backups do not, unless retained deliberately at deletion time.

**Point-in-time recovery** restores to a **new instance**. It does not roll the existing one back. Recovery therefore involves restoring, verifying, and repointing the application, which is why RTO for a logical corruption event is measured in tens of minutes rather than seconds.

**Aurora Backtrack** rewinds an Aurora MySQL cluster in place to a previous point, within a configured window, without restoring to a new instance. It is the fastest recovery from an accidental data change and is available for Aurora MySQL only.

**Cross-Region and cross-account copies.** Snapshots can be copied to another Region for disaster recovery and to another account for protection against a compromised account. An encrypted snapshot copied across Regions must be re-encrypted with a key in the destination Region.

**AWS Backup** centralizes backup policy across RDS, Aurora, DynamoDB, EFS, EBS, FSx, and more, with plans, vaults, retention rules, and Vault Lock for immutability.

**Setting RPO and RPO-appropriate design**

| RPO required | Approach |
| --- | --- |
| Up to 24 hours | Daily automated backups |
| Minutes | Automated backups with point-in-time recovery |
| Near zero within a Region | Multi-AZ synchronous replication |
| Near zero across Regions | Aurora Global Database, or DynamoDB global tables |

---

## 20.8 End-of-Chapter Questions

**Q1.** An application on Amazon RDS for PostgreSQL must survive the loss of an Availability Zone with automatic failover in under a minute, and must also serve read queries from the standby. Which deployment fits?

- A. Multi-AZ DB instance deployment
- B. Multi-AZ DB cluster deployment
- C. A single instance with a cross-Region read replica
- D. A single instance with automated backups

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* The Multi-AZ DB cluster provides two readable standbys and typically fails over in under 35 seconds; the Multi-AZ instance deployment keeps its standby idle.

**Q2.** A DynamoDB table uses the current date as its partition key. Write throughput is throttled despite provisioned capacity being well above the total workload. What is the cause?

- A. The table needs a local secondary index
- B. All writes for a given day target one partition, creating a hot partition
- C. On-demand capacity mode is required for writes
- D. The item size exceeds 400 KB

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* A low-cardinality partition key concentrates traffic on one partition; adding a suffix or choosing a higher-cardinality attribute spreads it.

**Q3.** An existing unencrypted RDS instance must be encrypted at rest to satisfy a new compliance requirement. What is the correct approach?

- A. Enable encryption on the running instance through Modify
- B. Create a snapshot, copy the snapshot with encryption enabled, and restore a new instance from the encrypted copy
- C. Enable encryption on the read replica and promote it
- D. Attach a KMS key to the DB subnet group

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Encryption cannot be enabled on an existing instance, so the snapshot copy and restore path is the only route.

**Q4.** An application makes repeated identical queries against DynamoDB and requires microsecond response times without changing application code. What should be added?

- A. ElastiCache for Redis
- B. A global secondary index
- C. DynamoDB Accelerator
- D. Read replicas

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* DAX is API-compatible with DynamoDB, so it requires no application change, and it delivers microsecond reads; DynamoDB has no read replicas.

**Q5.** A team accidentally deletes rows from an Aurora MySQL cluster and needs the fastest possible recovery to the state a few minutes earlier. Which option minimizes recovery time?

- A. Restore the latest automated backup to a new cluster
- B. Promote a read replica
- C. Use Aurora Backtrack to rewind the cluster in place
- D. Fail over to the Multi-AZ standby

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Backtrack rewinds in place without restoring to a new cluster; failover does not help because the deletion replicated to the standby immediately.

**Q6.** A workload has unpredictable traffic with long idle periods and occasional sharp bursts, and the team wants to avoid capacity planning. Which DynamoDB configuration is appropriate?

- A. Provisioned capacity sized for the peak
- B. Provisioned capacity with Auto Scaling
- C. On-demand capacity mode
- D. Provisioned capacity with reserved capacity purchased

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* On-demand bills per request and absorbs bursts without configuration, which suits unpredictable traffic with idle periods; provisioned modes require a capacity estimate.
