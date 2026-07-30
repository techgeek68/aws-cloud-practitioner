# Chapter 18: Designing the Storage Layer

---

Chapter 11 covered what each storage service is and how to use it. This chapter is about choosing between them under constraint, which is what the exam asks. The service definitions, storage class list, and lab procedures are in Chapter 11 and are not repeated.

The cafe from section 16.5 needs somewhere to put its website, its employee documents, its point-of-sale exports, and seven years of accounting records. Those are four different storage answers.

---

## 18.1 Choosing a Storage Service

Start with the access pattern, not the data volume.

| The requirement says | Choose | Because |
| --- | --- | --- |
| A single instance needs a disk, a boot volume, or a database file | Amazon EBS | Block storage attaches to one instance and supports a file system |
| Many instances need the same files at the same time | Amazon EFS | Shared POSIX file system across Availability Zones |
| A Windows workload needs an SMB share with Active Directory permissions | Amazon FSx for Windows File Server | EFS does not support Windows or SMB |
| High performance computing or ML training needs a fast scratch file system | Amazon FSx for Lustre | Sub-millisecond latency, and can present S3 objects as files |
| Unstructured objects reached over HTTP, at any scale | Amazon S3 | Object storage with no capacity to provision |
| Temporary scratch data, and losing it is acceptable | EC2 instance store | Fastest and already paid for, but gone on stop |
| On-premises systems need AWS storage behind a familiar protocol | AWS Storage Gateway | Presents S3 or EBS as NFS, SMB, iSCSI, or a tape library |

**Three questions that resolve most scenarios**

1. How many clients need it simultaneously? One means block, many means file.
2. Does the application need a file system, or can it use an API? A file system rules out S3.
3. Does the data need to survive the compute? If yes, instance store is out.

**The traps**

- **EFS for a database.** It is a shared file system with network latency per operation. Database data files belong on EBS.
- **S3 for a file system.** S3 is not mountable in the ordinary sense. Mountpoint for Amazon S3 exists, but it is optimized for large sequential reads and does not provide full POSIX semantics.
- **EBS for shared access.** Multi-Attach exists on io1 and io2 within one Availability Zone, but it requires a cluster-aware file system. It is not a general answer to "several instances need the same data."

---

## 18.2 Selecting an S3 Storage Class

The classes are defined in section 11.4. Selection turns on four questions.

1. **How often is the object read?** Frequently means Standard. A few times a year means an archive class.
2. **How quickly must it come back?** Milliseconds rules out Glacier Flexible Retrieval and Deep Archive.
3. **Can it be regenerated if lost?** If not, single-zone classes are out.
4. **Is the pattern known?** If not, Intelligent-Tiering removes the need to guess.

| Scenario | Class |
| --- | --- |
| Active website assets and application data | S3 Standard |
| Access pattern unknown, changing, or unpredictable | S3 Intelligent-Tiering |
| Backups needed within minutes, read a few times a year | S3 Standard-IA |
| Derived data such as transcoded copies or thumbnails, regenerable | S3 One Zone-IA |
| Archives that must be retrievable immediately, read about quarterly | S3 Glacier Instant Retrieval |
| Disaster recovery copies, retrieval in minutes to hours acceptable | S3 Glacier Flexible Retrieval |
| Seven-year compliance retention, retrieval in hours acceptable | S3 Glacier Deep Archive |
| Latency-critical analytics on hot data in one zone | S3 Express One Zone |

**The two cost traps**

- **Minimum billable duration.** Standard-IA and One Zone-IA bill a minimum of 30 days per object, Glacier Instant Retrieval 90 days, Glacier Flexible Retrieval 90 days, and Deep Archive 180 days. Moving short-lived objects into these classes costs more, not less.
- **Retrieval charges.** The infrequent access and archive classes charge per GB retrieved. Data read more often than expected can cost more in retrieval than was saved in storage. Intelligent-Tiering has no retrieval charge, which is why it is the safe answer when the pattern is genuinely unknown.

**Storage class analysis** in the S3 console reports observed access patterns per prefix and recommends when a lifecycle transition would pay. Use it rather than guessing.

---

## 18.3 Lifecycle and Intelligent-Tiering Strategy

**Lifecycle rules** apply a schedule you decide. Use them when the access pattern is predictable by age, which covers most logs, backups, and records.

A typical rule set for operational logs:

| Age | Action |
| --- | --- |
| 0 to 30 days | S3 Standard, actively queried |
| 30 days | Transition to Standard-IA |
| 90 days | Transition to Glacier Flexible Retrieval |
| 365 days | Transition to Glacier Deep Archive |
| 2,555 days | Expire |

**Intelligent-Tiering** moves objects for you based on observed access, for a small monitoring charge per object. It has a frequent tier, an infrequent tier after 30 days without access, and an archive instant access tier after 90 days. Optional Archive Access and Deep Archive Access tiers can be enabled at 90 and 180 days.

**Choosing between them**

- Predictable by age, and objects are large: **lifecycle rules**, since there is no per-object monitoring charge.
- Unpredictable, or a mix of hot and cold objects in the same prefix: **Intelligent-Tiering**.
- Very large numbers of very small objects: **lifecycle rules**, because the per-object monitoring charge on Intelligent-Tiering does not pay on objects under 128 KB, which are never moved to a lower tier anyway.

**The rule everyone forgets.** Add a lifecycle rule to abort incomplete multipart uploads after seven days. These are billed as stored data and do not appear in the object list, so they accumulate silently. On a bucket receiving large uploads this is often the single largest piece of unexplained spend.

**Expiring noncurrent versions.** On a versioned bucket, old versions accumulate forever unless a rule removes them. A rule expiring noncurrent versions after a defined period is part of any versioning design.

---

## 18.4 S3 Security Design

**Defaults, which are good.** Buckets and objects are private. Server-side encryption with SSE-S3 is applied automatically. ACLs are disabled on new buckets through the bucket owner enforced setting.

**Block Public Access** should be on at the account level, with any exception made deliberately at bucket level. It overrides bucket policies and ACLs, which makes it the one control that cannot be defeated by a careless policy edit.

**Choosing an access mechanism**

| Requirement | Mechanism |
| --- | --- |
| An AWS identity in this account needs access | Identity-based IAM policy |
| A principal in another account needs access | Bucket policy naming that principal, plus their identity policy |
| An external person with no AWS credentials needs temporary access to one object | Presigned URL |
| Many applications need different scoped access to one large bucket | S3 Access Points, one per application |
| A public static website | Bucket policy allowing `s3:GetObject`, with Block Public Access relaxed |
| A public website that should not expose the bucket | CloudFront with origin access control, Block Public Access left on |

The last two rows matter. Serving a website directly from a public bucket works and is what section 11.12 demonstrates, but production should use CloudFront with origin access control, so the bucket stays private and the distribution is the only path to it. Section 24.3 covers the configuration.

**Choosing an encryption method**

| Requirement | Option |
| --- | --- |
| Encryption at rest with no key management | SSE-S3, the default |
| Audit trail of every decryption, and control over who can use the key | SSE-KMS |
| Keys supplied per request and never stored by AWS | SSE-C |
| AWS must never hold plaintext or keys | Client-side encryption |

SSE-KMS is the usual production answer, because every use of the key is logged to CloudTrail and the key policy is a second gate independent of S3 permissions. Its cost consideration is the KMS request charge on high-volume workloads, which **S3 Bucket Keys** reduce substantially by caching a bucket-level key.

**Enforcing encryption on upload.** A bucket policy condition on `s3:x-amz-server-side-encryption` rejects unencrypted PUTs, which is stronger than default encryption because it fails loudly rather than silently correcting.

**S3 Object Lock** provides write-once-read-many retention. Governance mode can be overridden by a principal holding `s3:BypassGovernanceRetention`; compliance mode cannot be overridden by anyone, including the root user, until retention expires. It must be enabled when the bucket is created.

---

## 18.5 S3 Performance Design

**Request rate.** S3 supports at least 3,500 PUT, COPY, POST, and DELETE and 5,500 GET and HEAD requests per second per partitioned prefix, and prefixes scale horizontally. Spreading keys across prefixes multiplies throughput. The old advice about randomizing key prefixes to avoid hot partitions no longer applies, because S3 partitions automatically.

**Multipart upload** is recommended for objects of 100 MB or larger and required above 5 GB. It improves throughput through parallel part uploads, allows recovery from a network failure without restarting, supports pause and resume, and permits an upload to begin before the final size is known.

**S3 Transfer Acceleration** routes uploads through the nearest CloudFront edge location and onto the AWS backbone. AWS reports speed improvements of 50% to 500% for long-distance transfers. It carries a per-GB charge, so it is worth it for genuinely distant clients and wasted on same-Region traffic. The S3 Transfer Acceleration speed comparison tool will tell you whether it helps for a given Region.

**Byte-range fetches** retrieve part of an object, which parallelizes downloads and avoids transferring a whole file when only a header is needed.

**S3 Select** is closed to new customers as of July 2024. Use **Amazon Athena** to query object contents instead.

**S3 Object Lambda** runs a Lambda function on objects as they are retrieved, for redaction, format conversion, or per-caller filtering, without storing a second copy.

**Mountpoint for Amazon S3** presents a bucket as a file system for applications doing large sequential reads. It is not a general-purpose file system and does not replace EFS.

---

## 18.6 Replication Design

**Cross-Region Replication (CRR)** copies objects to a bucket in another Region. Use it for disaster recovery, for reducing latency to users in another geography, and for meeting a requirement that data exist in two Regions.

**Same-Region Replication (SRR)** copies within a Region. Use it to aggregate logs from several buckets, to maintain a copy in a separate account for protection against deletion, or to satisfy a data sovereignty rule requiring redundancy without leaving the Region.

**Requirements**

- Versioning enabled on both source and destination.
- An IAM role granting S3 permission to read the source and write the destination.
- For cross-account replication, a destination bucket policy permitting the source account.

**What replication does not do**

- It does not copy objects that existed before the rule was created. Backfilling requires **S3 Batch Replication**.
- It does not replicate objects encrypted with SSE-C.
- It does not replicate deletes of specific versions, only delete markers, and only if that option is enabled.
- It is not synchronous. There is no guarantee an object exists at the destination at any given moment.

**S3 Replication Time Control (RTC)** adds a service level agreement that 99.99% of objects replicate within 15 minutes, with metrics and events for objects that miss it. This is the answer when a question specifies a replication time commitment.

**Versioning as protection, not backup.** Versioning protects against overwrite and deletion within a bucket. It does not protect against the bucket being deleted or the account being compromised. A design requiring genuine protection replicates to a separate account with different credentials, ideally with Object Lock at the destination.

---

## 18.7 Shared File Storage Design

**Amazon EFS design decisions**

| Decision | Guidance |
| --- | --- |
| Regional or One Zone | Regional for anything that matters; One Zone only for regenerable data, at lower cost |
| Performance mode | General Purpose by default; Max I/O only for massively parallel workloads, and it cannot be changed later |
| Throughput mode | Elastic by default; Provisioned only where a measured sustained rate is required, and it bills whether used or not |
| Storage classes | Enable lifecycle management so untouched files move to Infrequent Access and Archive |
| Access control | Access points to enforce a POSIX identity and root directory per application, plus a file system policy requiring TLS |

**Amazon FSx selection**

| Requirement | Option |
| --- | --- |
| Windows shares with Active Directory and NTFS permissions | FSx for Windows File Server |
| HPC or ML training needing very high throughput, optionally linked to S3 | FSx for Lustre |
| NFS and SMB together, with snapshots, cloning, and deduplication | FSx for NetApp ONTAP |
| NFS with ZFS snapshots and low latency | FSx for OpenZFS |

The exam signal for FSx is a protocol or a platform: the word **SMB** or **Windows** points to FSx for Windows File Server, and **HPC**, **Lustre**, or **machine learning training** points to FSx for Lustre.

---

## 18.8 Hybrid Storage Design

| Requirement | Service |
| --- | --- |
| On-premises applications need an NFS or SMB share backed by S3, with local caching | Storage Gateway, file gateway |
| On-premises applications need iSCSI block volumes backed by AWS | Storage Gateway, volume gateway |
| Replace a physical tape library while keeping existing backup software | Storage Gateway, tape gateway |
| Scheduled or one-off transfer of large datasets over the network | AWS DataSync |
| Existing SFTP or FTPS clients must keep working, with data landing in S3 or EFS | AWS Transfer Family |
| Terabytes to petabytes, and the network cannot carry it in the time available | AWS Snow Family, subject to the availability limits in section 1.6.6 |

**Choosing between DataSync and Snow.** Work out how long the transfer would take over the available bandwidth. If that exceeds the deadline, the answer is physical transfer. If it does not, DataSync is faster to set up, has no shipping delay, and can run on a schedule for ongoing synchronization.

**AWS Transfer Family** supports SFTP, FTPS, FTP, and AS2, scales without managing servers, and requires no change to the applications sending the files. Its use cases are third-party uploads into a data lake, supply chain data exchange, and content distribution.

---

## 18.9 Storage Design Checklist

- Access pattern determined the service, not familiarity or habit.
- Storage class matches read frequency, retrieval time, and whether the data is regenerable.
- Lifecycle rules exist, including one aborting incomplete multipart uploads.
- Noncurrent version expiry is configured on versioned buckets.
- Block Public Access is on at account level, with exceptions documented.
- Encryption at rest is enabled, with SSE-KMS where an audit trail is required.
- Replication targets a separate account where the risk being managed is deletion or compromise, not just Region failure.
- Backup exists and has been restored from at least once.

---

## 18.10 End-of-Chapter Questions

**Q1.** A company stores transcoded video thumbnails that can be regenerated from source files at any time. They are accessed roughly twice a month. Which storage class minimizes cost?

- A. S3 Standard
- B. S3 Standard-Infrequent Access
- C. S3 One Zone-Infrequent Access
- D. S3 Glacier Deep Archive

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* The data is infrequently accessed and regenerable, so single-zone storage is acceptable and cheaper than Standard-IA; Deep Archive would not meet the access frequency without retrieval delay and charges.

**Q2.** A bucket receives large uploads from a mobile application, and the bill shows storage far exceeding the total size of the objects listed. What is the most likely cause?

- A. Versioning is retaining old object versions
- B. Incomplete multipart uploads are accumulating and being billed as stored data
- C. Cross-Region Replication is duplicating objects into the same bucket
- D. Server-side encryption increases stored size

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Incomplete multipart uploads do not appear in the object list but are billed; a lifecycle rule aborting them after a few days is the fix.

**Q3.** An architect must serve a static website publicly while keeping the S3 bucket private and Block Public Access enabled. What should be used?

- A. An S3 bucket policy granting `s3:GetObject` to `*`
- B. Presigned URLs generated for every object
- C. A CloudFront distribution with origin access control in front of the bucket
- D. S3 Transfer Acceleration

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Origin access control lets CloudFront read the bucket while the bucket remains private and Block Public Access stays on.

**Q4.** Cross-Region Replication has been enabled on a bucket holding 200,000 existing objects, and none of them appear at the destination. What is required?

- A. Enable versioning on the source bucket
- B. Recreate the replication rule with a different IAM role
- C. Run S3 Batch Replication to copy the pre-existing objects
- D. Change the destination bucket to the same Region

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Replication rules apply only to objects written after the rule exists; existing objects are backfilled by a batch operation.

**Q5.** A Windows application requires an SMB file share with Active Directory authentication and NTFS permissions. Which service should be used?

- A. Amazon EFS
- B. Amazon FSx for Windows File Server
- C. Amazon S3 with Mountpoint
- D. Amazon EBS Multi-Attach

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* EFS supports NFS only and does not work with Windows instances; FSx for Windows File Server provides SMB with AD integration.

**Q6.** A compliance rule requires that financial records cannot be deleted or modified by anyone, including administrators, for seven years. Which configuration meets this?

- A. Versioning with MFA delete on an existing bucket
- B. A bucket policy denying `s3:DeleteObject` to all principals
- C. S3 Object Lock in compliance mode, with the bucket created with Object Lock enabled
- D. Cross-Region Replication to a second account

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Compliance mode cannot be overridden by any principal until retention expires, and Object Lock can only be enabled at bucket creation.
