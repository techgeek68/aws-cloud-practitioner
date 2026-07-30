# Chapter 25: Decoupled and Event-Driven Architectures

---

Section 14.7 named the integration services. This chapter covers choosing between them and designing with them, which is a recurring theme across the High-Performing and Resilient domains.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 25.1 Why Decouple

A tightly coupled system, where component A calls component B directly and waits, fails in predictable ways:

- If B is down, A fails. The failure propagates.
- If B is slow, A is slow. Latency propagates.
- If B cannot keep up with A, requests are lost.
- A and B must scale together, because A blocks on B.

Placing a queue or a topic between them changes this:

- **Failure isolation.** B being down means messages accumulate, not that A fails. B processes them when it recovers.
- **Independent scaling.** A produces at its rate, B consumes at its rate, and the buffer absorbs the difference.
- **Load buffering.** A traffic spike fills the queue rather than overwhelming B.
- **Independent deployment.** B can be replaced without A knowing.

The cost is complexity: asynchronous systems are harder to trace, harder to reason about, and require handling of duplicates, ordering, and failed messages. Decouple where the coupling causes a real problem, not reflexively.

---

## 25.2 Amazon SQS

A fully managed message queue. Producers send messages; consumers poll and process them.

**Standard versus FIFO**

| | Standard | FIFO |
| --- | --- | --- |
| Throughput | Nearly unlimited | 300 per second, or 3,000 batched, higher with high-throughput mode |
| Ordering | Best effort | Strict, per message group |
| Delivery | At least once, duplicates possible | Exactly once |
| Use | Maximum throughput, order not critical | Order matters, or duplicates cannot be tolerated |

The default choice is Standard. Choose FIFO only when the question states that order matters or that a message must not be processed twice, because FIFO's throughput ceiling and cost are real.

**Mechanics that decide questions**

- **Visibility timeout.** When a consumer receives a message, it becomes invisible to others for this period. If processing finishes, the consumer deletes it; if the consumer crashes, the timeout expires and the message reappears. Set it longer than the longest expected processing time, or a slow message gets processed twice.
- **Long polling.** Waiting for messages to arrive rather than returning immediately reduces empty responses and cost. Set a wait time above zero; short polling is rarely the right choice.
- **Dead-letter queue.** After a configured number of failed processing attempts, a message moves to a DLQ for inspection rather than looping forever. Every production queue should have one.
- **Message retention.** Up to 14 days, default 4. A consumer offline longer than retention loses messages.
- **Delay queues** hide a message for a period after it is sent, for deferred processing.
- **Message size** is up to 256 KB; larger payloads go in S3 with the Extended Client Library storing a pointer.

**SQS does not push.** Consumers poll. For push delivery, use SNS or EventBridge, or let Lambda poll the queue through an event source mapping.

---

## 25.3 Amazon SNS

A managed publish and subscribe service. Publishers send to a topic; every subscriber receives a copy.

- **Fan-out** is the core pattern: one message to a topic, delivered to many subscribers at once. A common design publishes to SNS with several SQS queues subscribed, so each consumer gets its own durable copy to process at its own rate.
- **Subscribers** can be SQS queues, Lambda functions, HTTP and HTTPS endpoints, email, SMS, and mobile push.
- **Message filtering** lets each subscription receive only messages matching a filter policy, so one topic serves many consumers who each care about different messages, without a topic per consumer.
- **FIFO topics** preserve ordering and deduplicate, and can only deliver to FIFO SQS queues.
- **Message size** is up to 256 KB, with the same S3 extension for larger payloads.

**SNS versus SQS.** SNS pushes one message to many subscribers immediately and does not retain it beyond delivery attempts. SQS holds messages until one consumer processes each. The fan-out pattern combines them: SNS for the fan-out, an SQS queue per consumer for durability and independent pace.

---

## 25.4 Amazon EventBridge

An event bus that routes events from AWS services, SaaS partners, and custom applications to targets, by rule.

- **Rules** match events on their content and route to targets: Lambda, SQS, SNS, Step Functions, Kinesis, and many more.
- **The default event bus** receives events from AWS services automatically. **Custom buses** carry application events. **Partner buses** receive events from SaaS providers such as Datadog, Zendesk, and Shopify.
- **Content-based filtering** matches on any field in the event, which is richer than SNS filter policies.
- **Schema registry** discovers event structure and generates code bindings.
- **EventBridge Scheduler** runs scheduled events at scale, with one-time and recurring schedules, time zones, and flexible windows. It supersedes CloudWatch Events scheduled rules for new work.
- **EventBridge Pipes** connect one source to one target with optional filtering and enrichment, for point-to-point integration without glue code.

**EventBridge versus SNS.** Both do fan-out. EventBridge has far richer content-based routing, native SaaS and AWS service integration, schema discovery, and scheduling. SNS has higher throughput, lower latency, and supports SMS, email, and mobile push, which EventBridge does not. For routing AWS service events or SaaS events by content, EventBridge. For high-throughput application fan-out to queues, or for direct notification to people, SNS.

---

## 25.5 AWS Step Functions

Orchestrates multiple services into a workflow defined as a state machine, handling sequencing, branching, parallelism, retries, and error handling declaratively.

| | Standard workflows | Express workflows |
| --- | --- | --- |
| Duration | Up to 1 year | Up to 5 minutes |
| Execution rate | Up to 2,000 per second | Over 100,000 per second |
| Pricing | Per state transition | Per execution and duration |
| History | Full execution history retained | Limited, sent to CloudWatch Logs |
| Use | Long-running, auditable business processes | High-volume, short event processing |

**When Step Functions is the answer.** A process with several steps, conditional branches, parallel execution, or the need to wait, retry, and handle failure explicitly. It replaces the anti-pattern of one Lambda function calling another which calls another, where failure handling and visibility are lost.

**Orchestration versus choreography**

- **Orchestration**, with Step Functions, has a central coordinator that knows the whole workflow. Easier to understand, monitor, and change; the coordinator is a dependency.
- **Choreography**, with events, has each component react to events without a central controller. More loosely coupled and independently scalable; harder to see the end-to-end flow.

Neither is always right. Orchestration suits a defined business process with a clear sequence; choreography suits independent services reacting to things that happen.

---

## 25.6 Amazon MQ and Amazon Kinesis

**Amazon MQ** is managed Apache ActiveMQ and RabbitMQ. Choose it only when migrating an existing application that already speaks a standard messaging protocol such as JMS, AMQP, MQTT, or STOMP. For anything new on AWS, SQS and SNS are simpler, cheaper, and scale further. The exam signal for MQ is a migration of an existing message broker.

**Amazon Kinesis** is for streaming data, which is different from messaging.

| Service | Purpose |
| --- | --- |
| Kinesis Data Streams | Real-time ingestion of high-volume records, with multiple consumers reading the same data and replaying it |
| Amazon Data Firehose | Delivery of streaming data to S3, Redshift, OpenSearch, or Splunk, with no code and near-real-time batching |
| Kinesis Data Analytics | SQL or Apache Flink processing over a stream |

**A stream is not a queue.** The distinction the exam tests:

- A queue deletes a message once a consumer processes it. A stream retains records for a retention period, and many consumers can read the same records independently, and replay them.
- Kinesis preserves order within a shard and supports multiple independent consumers of the same data. SQS does neither.
- Use Kinesis for real-time analytics, log and event aggregation, and any case where several consumers need the same ordered stream or the ability to replay. Use SQS for distributing discrete work items to be processed once.

**Kinesis versus Amazon MSK.** MSK is managed Apache Kafka, chosen when Kafka compatibility or the Kafka ecosystem is required. Kinesis is simpler and more integrated with AWS; MSK is the answer when the question names Kafka.

---

## 25.7 Choosing a Decoupling Service

| Requirement | Service |
| --- | --- |
| Distribute work items, each processed once | Amazon SQS |
| Strict ordering or no duplicates on work items | SQS FIFO |
| One message delivered to many consumers | Amazon SNS, or SNS with SQS per consumer |
| Route AWS or SaaS events by content | Amazon EventBridge |
| Schedule tasks at scale | EventBridge Scheduler |
| Coordinate a multi-step workflow with branching and retries | AWS Step Functions |
| High-volume short workflows | Step Functions Express |
| Real-time stream with multiple consumers and replay | Amazon Kinesis Data Streams |
| Load streaming data into a store with no code | Amazon Data Firehose |
| Migrate an existing JMS or AMQP broker | Amazon MQ |
| Managed Apache Kafka | Amazon MSK |

**The decision questions**

1. Is this discrete work items, or a continuous stream? Items point to SQS; a stream points to Kinesis.
2. One consumer per message, or many? One points to SQS; many points to SNS, EventBridge, or Kinesis.
3. Does a consumer need to replay history? Only Kinesis and MSK retain and replay.
4. Is there a multi-step process to coordinate? Step Functions.
5. Is this routing events by their content? EventBridge.

---

## 25.8 End-of-Chapter Questions

**Q1.** An application must send each order to exactly one of a pool of worker processes, and orders must be processed in the sequence they were placed. Which service and configuration fits?

- A. Amazon SNS standard topic
- B. Amazon SQS standard queue
- C. Amazon SQS FIFO queue
- D. Amazon EventBridge

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* FIFO guarantees ordering and exactly-once processing; standard SQS is best-effort ordering with possible duplicates, and SNS delivers to all subscribers rather than one worker.

**Q2.** A single event must be delivered to four independent systems, each processing it at its own pace with durability if one is temporarily offline. Which design fits?

- A. An SQS queue polled by all four systems
- B. An SNS topic with four SQS queues subscribed, one per system
- C. Four separate SNS topics
- D. A Kinesis stream with four consumers

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* The fan-out pattern gives each consumer its own durable queue, so an offline consumer accumulates messages rather than losing them.

**Q3.** A workflow involves several steps with conditional branching, parallel tasks, and explicit retry and failure handling, running over several minutes. Which service should coordinate it?

- A. A chain of Lambda functions invoking each other
- B. Amazon SQS with a dead-letter queue
- C. AWS Step Functions
- D. Amazon EventBridge rules

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Step Functions handles sequencing, branching, parallelism, and error handling declaratively, avoiding the fragility of Lambda functions chaining themselves.

**Q4.** A consumer occasionally processes the same SQS message twice. The processing takes up to 90 seconds, and the queue's visibility timeout is 30 seconds. What is the cause?

- A. The queue should be FIFO
- B. The visibility timeout is shorter than the processing time, so the message reappears before processing completes
- C. Long polling is disabled
- D. The dead-letter queue is misconfigured

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* When processing exceeds the visibility timeout, the message becomes visible again and a second consumer picks it up; the timeout must exceed the maximum processing time.

**Q5.** A platform needs to ingest real-time clickstream data that several independent analytics consumers will read, with the ability to replay the last 24 hours. Which service fits?

- A. Amazon SQS
- B. Amazon SNS
- C. Amazon Kinesis Data Streams
- D. Amazon MQ

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Kinesis retains records for a retention period, supports multiple independent consumers of the same ordered data, and allows replay; SQS deletes messages once processed.

**Q6.** A company is migrating an on-premises application that communicates through JMS and AMQP and does not want to rewrite its messaging code. Which service should be used?

- A. Amazon SQS
- B. Amazon SNS
- C. Amazon MQ
- D. Amazon EventBridge

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Amazon MQ supports standard protocols such as JMS and AMQP, so the existing code works unchanged; SQS and SNS use AWS-specific APIs that would require a rewrite.
