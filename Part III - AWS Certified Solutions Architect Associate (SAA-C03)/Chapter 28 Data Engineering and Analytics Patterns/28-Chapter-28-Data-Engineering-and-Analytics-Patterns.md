# Chapter 28: Data Engineering and Analytics Patterns

---

Section 14.2 named the analytics services. This chapter covers assembling them into a pipeline, which the High-Performing Architectures domain tests through scenarios rather than through service definitions.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 28.1 Batch Versus Streaming

The latency requirement decides the architecture, and everything else follows from it.

| | Batch | Streaming |
| --- | --- | --- |
| Processing | Accumulated data at intervals | Records as they arrive |
| Latency | Minutes to hours | Seconds or less |
| Services | S3, AWS Glue, Amazon EMR, Amazon Athena | Kinesis Data Streams, Amazon Data Firehose, Amazon MSK |
| Suits | Daily reporting, ETL, historical analysis | Fraud detection, live dashboards, anomaly alerting, IoT telemetry |
| Cost | Lower per unit of data | Higher, since capacity runs continuously |

**Do not build streaming for batch requirements.** A dashboard refreshed hourly does not need a stream. Streaming costs more, is harder to operate, and is harder to reason about when something goes wrong. The question to ask is what decision the data supports and how quickly that decision must be made.

**Near real time is often the honest answer.** Amazon Data Firehose buffers by size or time, typically delivering within a minute or so, which satisfies most "real time" requirements at a fraction of the cost and complexity of true stream processing.

---

## 28.2 Ingestion

**Amazon Kinesis Data Streams**

- Records are distributed across **shards**, each supporting 1 MB per second or 1,000 records per second in, and 2 MB per second out.
- The **partition key** determines the shard, so a poorly chosen key creates a hot shard exactly as it does in DynamoDB.
- **Retention** is 24 hours by default, extendable to 365 days, which is what allows replay.
- **Enhanced fan-out** gives each consumer its own 2 MB per second per shard rather than sharing it, at additional cost.
- **On-demand mode** removes shard management and scales automatically, at a higher per-GB price. Provisioned mode is cheaper for steady, known throughput.

**Amazon Data Firehose**

- Fully managed delivery to S3, Redshift, OpenSearch, Splunk, and HTTP endpoints.
- Buffers by size or interval, and can convert format to Parquet or ORC, compress, and invoke a Lambda function to transform records in flight.
- No shards, no capacity to manage, no code to write.
- **It does not retain data**, so there is nothing to replay and no second consumer.

**The choice.** If several consumers need the same records, or replay is required, or custom processing must happen in the stream, use Data Streams. If the requirement is simply to land data somewhere reliably, use Firehose. Firehose can consume from a Data Stream, which is a common combination: the stream for consumers and replay, Firehose for archival to S3.

**Amazon MSK** is managed Apache Kafka, chosen when Kafka compatibility, existing Kafka tooling, or very long retention is required. It carries more operational surface than Kinesis and is the answer when a question names Kafka.

**Other ingestion paths**: AWS DMS with change data capture for replicating database changes, AWS IoT Core for device telemetry, and the CloudWatch Logs subscription filter for streaming log data into Kinesis or Firehose.

---

## 28.3 The S3 Data Lake

S3 is the storage layer for analytics because it decouples storage from compute, allowing several engines to read the same data without copying it.

**Zone layout**

| Zone | Contains |
| --- | --- |
| Raw or landing | Data exactly as received, never modified |
| Cleansed or curated | Validated, deduplicated, type-corrected |
| Curated or consumption | Aggregated and modeled for querying |

Keeping the raw zone immutable means a bug in transformation can be fixed by reprocessing rather than by re-ingesting.

**Partitioning** is the single largest determinant of query cost and speed. Athena and Redshift Spectrum charge by data scanned, and a partitioned layout lets them skip everything irrelevant.

```
s3://lake/events/year=2026/month=07/day=22/hour=14/
```

Partition on the columns queries filter by, most often date. Over-partitioning creates many small files and slows queries through metadata overhead; a reasonable target is files of 128 MB or larger.

**File formats**

| Format | Characteristics |
| --- | --- |
| CSV and JSON | Human readable, large, slow to query, no schema |
| Apache Parquet | Columnar, compressed, typed. The default for analytics |
| Apache ORC | Columnar, similar to Parquet, common in the Hive ecosystem |
| Apache Avro | Row-based, good for write-heavy streaming and schema evolution |

Converting JSON to Parquet typically reduces both storage and query cost substantially, because columnar formats let engines read only the columns a query touches. Firehose can perform this conversion during delivery.

**Table formats.** Apache Iceberg adds ACID transactions, schema evolution, and time travel over object storage, and is supported by Athena, EMR, Redshift, and Glue. It is the answer when a data lake needs row-level updates and deletes rather than append-only files.

**AWS Lake Formation** provides fine-grained permissions over the lake, down to table, column, row, and cell level, layered on the Glue Data Catalog. Use it when different teams must see different subsets of the same tables, which S3 bucket policies cannot express.

---

## 28.4 Transformation and Cataloging

**The AWS Glue Data Catalog** is the metadata layer: databases, tables, schemas, and partitions. Athena, Redshift Spectrum, EMR, and Glue jobs all read from it, which is what allows one definition of a table to serve every engine.

**Glue crawlers** scan data in S3, infer schema and partitions, and populate the catalog. Run them on a schedule or after ingestion. They are convenient and imperfect: inferred types are sometimes wrong, and for stable schemas defining tables explicitly is more reliable.

**Glue ETL jobs** run Apache Spark or Python shell scripts serverlessly, with **Glue Studio** for visual authoring and **job bookmarks** to track what has already been processed so reruns do not duplicate work.

**Choosing a transformation engine**

| Situation | Engine |
| --- | --- |
| Serverless ETL, moderate volume, no cluster wanted | AWS Glue |
| Large-scale Spark, Hive, or Presto with cluster control and Spot capacity | Amazon EMR |
| Transformation expressible as SQL over data already in S3 | Amazon Athena with `CREATE TABLE AS SELECT` |
| Lightweight per-record transformation during delivery | Firehose with a Lambda transform |
| Complex multi-step orchestration | AWS Step Functions coordinating the above |

**EMR versus Glue.** Glue is serverless and simpler; EMR gives control over instance types, versions, and cluster configuration, supports Spot for large cost savings, and suits sustained heavy workloads. For intermittent jobs, Glue. For a data platform running Spark continuously, EMR is usually cheaper.

---

## 28.5 Query and Analysis

**Amazon Athena**

- Serverless SQL over S3, using the Glue Data Catalog, billed per terabyte scanned.
- Reduce cost by partitioning, using columnar formats, compressing, and selecting only needed columns. `SELECT *` on an unpartitioned CSV lake is the expensive way to do everything.
- **Athena federated query** reaches other sources such as RDS, DynamoDB, and Redshift through connectors.
- Suits ad hoc exploration, log analysis, and infrequent queries.

**Amazon Redshift**

- A provisioned or serverless data warehouse for repeated complex analytical queries over structured data.
- **Redshift Spectrum** queries S3 directly from Redshift, so hot data lives in the warehouse and cold data stays in the lake.
- **Distribution and sort keys** determine performance: distribution controls how rows spread across nodes, sort keys control how efficiently ranges are scanned.
- **Zero-ETL integrations** from Aurora, RDS, and DynamoDB replicate data into Redshift continuously without a pipeline.

**Athena or Redshift.** Athena for infrequent or exploratory queries where paying per query beats paying for a cluster. Redshift for repeated, complex, latency-sensitive analytics where a warehouse's optimizations pay for themselves. Volume alone does not decide it; query frequency does.

**Amazon OpenSearch Service** for full-text search, log analytics, and operational dashboards, where the access pattern is search and aggregation over recent data rather than large historical scans.

**Amazon QuickSight** for dashboards, with **SPICE** as an in-memory cache so dashboards do not query the source on every view.

---

## 28.6 A Worked Pipeline

The cafe from section 16.5 wants to know which products sell together, and to see today's sales as they happen.

**Requirements**

- Point-of-sale transactions arrive continuously.
- A live dashboard shows today's takings, refreshed within a minute.
- Analysts run historical queries over several years of data.
- Cost matters; the business is small.

**The design**

1. **Ingest.** The point-of-sale system writes transactions to a **Kinesis Data Stream**, so both the live dashboard and the archival path read the same records.
2. **Archive.** **Data Firehose** consumes the stream, converts records to **Parquet**, and writes to `s3://cafe-lake/raw/transactions/year=/month=/day=/`.
3. **Live view.** A **Lambda** function consumes the stream, aggregates running totals, and writes to a **DynamoDB** table the dashboard reads. This gives second-level freshness without a second analytics system.
4. **Catalog.** A **Glue crawler** runs daily and updates the table definition and partitions.
5. **Transform.** A nightly **Glue job** cleans the raw data, deduplicates, and writes a curated dataset partitioned by date.
6. **Query.** Analysts use **Athena** against the curated zone. Because it is partitioned Parquet, a query over one month scans a small fraction of the lake.
7. **Visualize.** **QuickSight** connects to Athena for historical dashboards and to DynamoDB for the live view.
8. **Lifecycle.** An S3 lifecycle rule moves raw data to Glacier Instant Retrieval after 90 days and Deep Archive after a year, as covered in section 18.3.

**Why not Redshift here.** Query volume is low and irregular. Athena costs nothing when nobody is querying; a Redshift cluster costs the same at 3am as at noon. If the analysts moved to running hundreds of queries daily, the arithmetic would reverse.

---

## 28.7 End-of-Chapter Questions

**Q1.** A company must land streaming data into Amazon S3 in Parquet format with no code to manage and no requirement to replay records. Which service fits?

- A. Amazon Kinesis Data Streams
- B. Amazon Data Firehose
- C. Amazon MSK
- D. Amazon SQS

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Firehose delivers to S3 with format conversion and no capacity to manage; Data Streams and MSK add retention and replay that this requirement does not need.

**Q2.** Athena queries over a data lake are slow and expensive. The data is stored as uncompressed JSON in a single S3 prefix. What change gives the largest improvement?

- A. Increase the Athena query timeout
- B. Partition the data by date and convert it to compressed Parquet
- C. Move the data to Amazon Redshift
- D. Enable S3 Transfer Acceleration

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Athena bills by data scanned, so partitioning lets it skip irrelevant files and a columnar format lets it read only the required columns.

**Q3.** Several independent applications must each process the same stream of clickstream records, and one must be able to reprocess the last three days after a bug fix. Which service supports this?

- A. Amazon SQS with multiple consumers
- B. Amazon Data Firehose with multiple destinations
- C. Amazon Kinesis Data Streams with extended retention
- D. Amazon SNS with multiple subscriptions

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Kinesis retains records for a configurable period and allows several independent consumers to read and replay the same shard data.

**Q4.** An analytics team runs a handful of exploratory queries per week over data already in Amazon S3 and wants to avoid paying for idle infrastructure. Which service fits?

- A. Amazon Redshift provisioned cluster
- B. Amazon EMR with a long-running cluster
- C. Amazon Athena
- D. Amazon OpenSearch Service

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Athena is serverless and billed per query, so infrequent use costs nothing between queries, unlike a provisioned cluster.

**Q5.** Different teams must query the same Glue Data Catalog tables but each may only see certain columns and rows. Which service enforces this?

- A. S3 bucket policies
- B. AWS Lake Formation
- C. IAM policies on the Glue Data Catalog
- D. Athena workgroups

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Lake Formation provides table, column, row, and cell-level permissions, which bucket policies and coarse IAM permissions cannot express.

**Q6.** A Kinesis Data Stream shows one shard throttling while others are idle. What is the most likely cause?

- A. The stream is in on-demand mode
- B. The partition key has low cardinality, concentrating records on one shard
- C. Enhanced fan-out is disabled
- D. Retention is set too long

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* The partition key determines shard assignment, so a low-cardinality key creates a hot shard in the same way a poor DynamoDB partition key creates a hot partition.
