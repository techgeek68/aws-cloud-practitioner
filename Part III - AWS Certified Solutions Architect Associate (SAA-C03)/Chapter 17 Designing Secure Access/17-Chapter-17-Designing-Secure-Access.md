# Chapter 17: Designing Secure Access

---

Design Secure Architectures is 30% of SAA-C03, the heaviest domain. Chapter 8 established what IAM is. This chapter is about designing with it: how permissions are actually evaluated, how access is granted across accounts and to external identities, and how data is protected and audited.

The shared responsibility model, the IAM component definitions, and the basic policy structure are all in Chapter 8 and are not repeated here.

---

## 17.1 Least Privilege in Practice

The principle is easy to state and hard to apply. Grant only the permissions a task requires, start from the minimum, add as needed, and revoke what is no longer used.

**How to narrow a policy, in order**

1. **Start from deny.** Nothing is permitted by default, so the question is only ever what to add.
2. **Scope the actions.** Replace `s3:*` with the specific operations the workload performs. If it only reads, `s3:GetObject` and `s3:ListBucket` are the whole list.
3. **Scope the resources.** Replace `"Resource": "*"` with the ARNs actually touched. For S3 this means naming both the bucket and the objects inside it, since they are separate ARNs.
4. **Add conditions.** Constrain by source IP, whether MFA was used, a required tag, or the time of day.
5. **Verify with data.** IAM Access Analyzer can generate a policy from CloudTrail activity, showing what the identity actually used rather than what someone guessed it needed.

**A worked narrowing**

Starting point, which is what most people write first:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "s3:*",
      "Resource": "*"
    }
  ]
}
```

After narrowing to what an image-processing application genuinely does:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ReadSourceObjects",
      "Effect": "Allow",
      "Action": ["s3:GetObject"],
      "Resource": "arn:aws:s3:::media-uploads/incoming/*"
    },
    {
      "Sid": "ListSourcePrefix",
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": "arn:aws:s3:::media-uploads",
      "Condition": {
        "StringLike": { "s3:prefix": "incoming/*" }
      }
    },
    {
      "Sid": "WriteProcessedObjects",
      "Effect": "Allow",
      "Action": ["s3:PutObject"],
      "Resource": "arn:aws:s3:::media-processed/output/*",
      "Condition": {
        "StringEquals": { "s3:x-amz-server-side-encryption": "aws:kms" }
      }
    }
  ]
}
```

Note what the second statement does. `ListBucket` is a bucket-level action, so its resource is the bucket ARN without a suffix, and the prefix condition is the only way to restrict which part of the bucket can be listed.

---

## 17.2 Policy Evaluation Logic

This is the most commonly misunderstood topic in the domain, and the source material for this course presented it as a simple left-to-right sequence, which it is not.

**The rules, in the order AWS applies them**

1. **Start from an implicit deny.** No permission exists until something grants it.
2. **Evaluate every applicable policy of every type.** Identity-based, resource-based, permission boundaries, service control policies, and session policies are all collected first.
3. **An explicit deny in any one of them ends the evaluation.** The request is denied, and no allow anywhere can override it.
4. **Check the service control policy.** If an SCP applies and does not allow the action, the request is denied regardless of what IAM permits.
5. **Check the resource-based policy.** In the same account, an allow here can be sufficient on its own for most services. Across accounts, both sides must allow.
6. **Check the permission boundary.** If one is attached, the action must be within it.
7. **Check the session policy.** If the credentials came from `AssumeRole` with a session policy, the action must be within that too.
8. **Check the identity-based policy.** An allow here, with nothing above having denied or excluded it, permits the request.
9. **If no allow was found, the implicit deny stands.**

**The two rules that answer most exam questions**

- **Explicit deny always wins.** It does not matter which policy type it came from or how many allows exist.
- **Boundaries and SCPs do not grant anything.** They only cap what an identity-based policy can grant. An SCP allowing `s3:*` gives nobody S3 access; it merely fails to prevent it.

**Worked examples**

| Identity policy | Resource policy | Result |
| --- | --- | --- |
| Allows `GetObject`, `PutObject`, `ListBucket` on bucket X | Allows `GetObject` and `ListBucket`, explicitly denies `PutObject` | `PutObject` denied; the explicit deny overrides the identity allow |
| Allows `ListBucket` on bucket Y | Allows `GetObject` and `ListBucket` on bucket Y | Both `GetObject` and `ListBucket` permitted; the resource policy grants access the identity policy did not |
| Allows `s3:*` | None | Permitted, since same-account access needs only one side to allow |
| Allows `s3:*` in account A | Bucket in account B has no policy naming account A | Denied; cross-account access requires both the identity policy and the resource policy to allow |

That last row is the one worth memorizing. **Within one account, either side is sufficient. Across accounts, both are required.**

---

## 17.3 Roles for Workloads

The rule from section 8.5 stands: if an AWS service needs to call another AWS service, use a role. What follows is which role, for which compute model.

| Compute | Mechanism | Notes |
| --- | --- | --- |
| Amazon EC2 | Instance profile | Credentials retrieved from instance metadata; always use IMDSv2 |
| AWS Lambda | Execution role | Also needs `AWSLambdaBasicExecutionRole` or equivalent to write logs |
| Amazon ECS | Task role for the application, plus a separate task execution role for pulling images and writing logs | These are two different roles and conflating them is a common error |
| Amazon EKS | IAM roles for service accounts (IRSA), or EKS Pod Identity | Scopes permissions to a Kubernetes service account rather than the whole node |
| AWS Batch, Glue, SageMaker | Service roles | Each defines its own trust relationship |

**Why this matters architecturally.** An access key on an instance is a credential that exists until someone removes it, can be copied off the instance, and appears in whatever configuration file or environment variable holds it. A role produces credentials that expire on their own and are never written down. When a question describes credentials in application code or in a configuration file, the answer is almost always a role.

---

## 17.4 Cross-Account Access

**The assume-role pattern**

1. Account B, holding the resource, creates a role with the permissions needed.
2. That role's **trust policy** names account A as a principal permitted to assume it.
3. An identity in account A is granted `sts:AssumeRole` on that role's ARN.
4. The identity calls `AssumeRole`, receives temporary credentials, and acts as the role in account B.

The trust policy is what makes this safe. Account B decides who may assume the role; account A cannot grant itself access.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": { "AWS": "arn:aws:iam::111122223333:root" },
      "Action": "sts:AssumeRole",
      "Condition": {
        "StringEquals": { "sts:ExternalId": "unique-value-agreed-between-parties" },
        "Bool": { "aws:MultiFactorAuthPresent": "true" }
      }
    }
  ]
}
```

**The external ID** exists to prevent the confused deputy problem. When a third party such as a managed service provider assumes a role in many customer accounts, an external ID unique to each customer stops one customer persuading the provider to act against another. Any question involving a third party assuming a role in your account should include it.

**AWS STS in brief**

- Issues temporary credentials consisting of an access key, a secret key, and a session token.
- `AssumeRole` for cross-account and workload access, `AssumeRoleWithSAML` for enterprise federation, `AssumeRoleWithWebIdentity` for web and mobile identity providers.
- Session duration is configurable, with a maximum set by the role.

**AWS Resource Access Manager (RAM)** is the other half of cross-account design and is frequently missed. Rather than granting API access, RAM shares the resource itself with another account or the whole organization. Shareable resources include VPC subnets, Transit Gateways, Route 53 Resolver rules, License Manager configurations, and Aurora DB clusters.

The distinction the exam draws: **assume-role gives another account permission to call APIs; RAM lets another account use a resource as though it were their own.** Sharing subnets so several accounts deploy into one centrally managed VPC is a RAM answer, not an IAM answer.

---

## 17.5 Workforce and Customer Identity

These are two different problems with two different services, and confusing them is a reliable way to lose marks.

### 17.5.1 AWS IAM Identity Center, for Workforce

- The recommended way to manage human access across many AWS accounts.
- Identity source can be its built-in directory, Microsoft Active Directory through AWS Directory Service, or an external provider such as Okta, Microsoft Entra ID, or Google Workspace through SAML 2.0 or SCIM.
- **Permission sets** define a collection of permissions and are assigned to a group for a given account, so one identity reaches many accounts without an IAM user in each.
- Users receive short-lived credentials, which removes the largest category of credential leakage.
- Supports CLI access through `aws sso login`, so engineers get temporary credentials on their laptops without an access key ever existing.

### 17.5.2 Amazon Cognito, for Application Users

- **User pools** are a user directory providing sign-up, sign-in, password reset, MFA, and social or enterprise federation. The output is a JSON Web Token the application validates. This is authentication for your application's users.
- **Identity pools**, also called federated identities, exchange a token for temporary AWS credentials, so an application user can call AWS services directly. This is authorization to reach AWS.

The two are used together: a user pool establishes who someone is, and an identity pool gives that person scoped AWS access. A mobile application uploading to a per-user S3 prefix is the standard example.

**The exam distinction.** Employees signing in to the AWS console across accounts is IAM Identity Center. Customers signing in to your application is Cognito. If the question mentions "millions of users" or "mobile app users," it is Cognito.

### 17.5.3 AWS Directory Service

- **AWS Managed Microsoft AD** is a full managed Active Directory in AWS, for workloads that need real AD, such as Amazon FSx for Windows File Server or SQL Server with Windows authentication.
- **AD Connector** is a proxy to an existing on-premises directory, storing nothing in AWS.
- **Simple AD** is a lightweight, Samba-based alternative for basic requirements.

---

## 17.6 Resource-Based Policies

Attached to a resource rather than an identity, these answer "who may use this?" rather than "what may this identity reach?"

**Services that support them:** Amazon S3 buckets, SQS queues, SNS topics, Lambda functions, KMS keys, Secrets Manager secrets, EFS file systems, API Gateway, ECR repositories, and CloudWatch Logs destinations.

**Why they matter in design**

- They are the only way to grant access to a principal in another account without that account's identity policy being the sole gate.
- They can grant access to anonymous principals, which is how public S3 content works and why Block Public Access exists as a separate override.
- They allow conditions on the resource side, so the resource owner enforces requirements such as TLS or a specific encryption method regardless of what the caller's own policy says.

**A KMS key policy** is a special case worth knowing: unlike other resource policies, a KMS key policy is mandatory, and by default it is the only thing granting access to the key. An IAM policy allowing `kms:Decrypt` achieves nothing unless the key policy also delegates to IAM. Questions where a user has KMS permissions but still gets access denied usually turn on this.

---

## 17.7 Data Security Controls

### 17.7.1 Encryption in Transit and at Rest

**In transit**

- TLS between clients and AWS, and between AWS services.
- Site-to-Site VPN for encrypted connectivity to on-premises networks.
- AWS Direct Connect provides a private path but is not encrypted by itself; pair it with a VPN or use MACsec where supported.

**At rest, server-side encryption.** The client sends data, AWS encrypts and stores it, and decrypts on request. Convenient, and the default for most services. Key sources are AWS managed keys, customer managed KMS keys, or customer-provided keys.

**At rest, client-side encryption.** The client encrypts before sending, so AWS never sees plaintext. Maximum control, and the client bears full responsibility for key management. The answer when a question says AWS must not be able to read the data under any circumstances.

### 17.7.2 AWS KMS

- **Customer managed keys** are created and controlled by you, with a key policy, rotation setting, and full CloudTrail visibility of every use.
- **AWS managed keys** are created per service on your behalf, are free, and cannot have their policy edited.
- **Automatic rotation** creates new key material annually while retaining old material so existing ciphertext stays readable.
- **Envelope encryption** is what actually happens under the covers: KMS generates a data key, the data key encrypts the data, and KMS encrypts the data key. This avoids sending large payloads to KMS and is why KMS scales.
- **Multi-Region keys** allow the same key material in several Regions, for cross-Region replication of encrypted data.

**AWS CloudHSM** is the answer when regulation demands single-tenant, FIPS 140-3 Level 3 hardware under your exclusive control. It is more work and should not be the default.

### 17.7.3 Secrets and Certificates

| Need | Service |
| --- | --- |
| Database credentials with automatic rotation | AWS Secrets Manager |
| Configuration values and simple secrets, low cost | AWS Systems Manager Parameter Store, SecureString type |
| TLS certificates for CloudFront, load balancers, and API Gateway | AWS Certificate Manager, with free public certificates and automatic renewal |
| Private certificate authority | AWS Private CA |

Secrets Manager costs more per secret than Parameter Store. It earns that when automatic rotation matters, particularly for RDS, Aurora, Redshift, and DocumentDB, where it integrates directly. Where a value never changes, Parameter Store is the cheaper right answer.

---

## 17.8 Designing for Auditability

**AWS CloudTrail**

- Records API activity: who called what, when, from where, and whether it succeeded.
- **Organization trails** capture every account in an AWS Organization into one bucket, which is the design answer whenever centralized audit is required.
- **Log file validation** produces a digest that proves logs were not altered.
- **CloudTrail Lake** stores and queries events with SQL without building a pipeline.
- Deliver to a bucket in a separate, tightly controlled logging account so an attacker with access to a workload account cannot erase the evidence.

**AWS Config**

- Records resource configuration over time and evaluates it against rules.
- **Conformance packs** apply a collection of rules as one unit.
- **Auto-remediation** through Systems Manager Automation fixes drift rather than only reporting it.
- Answers "what did this resource look like last Tuesday", which CloudTrail cannot.

**IAM Access Analyzer**

- Identifies resources shared with external principals, including buckets, roles, keys, and queues.
- Validates policies against best practice as they are written.
- Generates a least-privilege policy from observed CloudTrail activity.

**Amazon GuardDuty, Security Hub, Detective, and Macie** are covered in section 8.8. In design terms, GuardDuty detects, Security Hub aggregates and scores, Detective investigates, and Macie finds sensitive data that should not be where it is.

---

## 17.9 Security Design Checklist

Apply to any architecture under review.

**Identity**

- Root user has MFA and no access keys, and is not used for daily work.
- Human access is through IAM Identity Center, not IAM users with long-lived keys.
- Workloads use roles; no access keys exist on instances or in code.
- Permissions are attached to groups and roles, never directly to users.
- Permission boundaries or SCPs cap what can be granted where delegation exists.

**Data**

- Encryption at rest is on for every store holding anything sensitive.
- TLS is enforced, ideally by a resource policy condition rather than by convention.
- Secrets are in Secrets Manager or Parameter Store, not in environment variables committed to a repository.
- S3 Block Public Access is on at account level, with documented exceptions.

**Network**

- Resources sit in private subnets unless they have a reason to be public.
- Security groups reference other security groups rather than CIDR ranges.
- VPC endpoints keep AWS service traffic off the internet.

**Audit**

- An organization CloudTrail trail delivers to a dedicated logging account.
- AWS Config records configuration with rules for the controls that matter.
- Access Analyzer runs and its findings are triaged by someone.

---

## 17.10 End-of-Chapter Questions

**Q1.** An IAM user's identity policy allows `s3:PutObject` on a bucket. The bucket policy explicitly denies `s3:PutObject` for that user. What is the result of a PutObject request?

- A. Allowed, because identity policies take precedence
- B. Allowed, because the two policies are combined and one permits it
- C. Denied, because an explicit deny overrides any allow
- D. Denied, but only if the user is in a different account

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Explicit deny ends evaluation immediately, regardless of which policy type it came from.

**Q2.** An application in account A must read objects from an S3 bucket in account B. What is required?

- A. An identity policy in account A allowing the S3 actions
- B. A bucket policy in account B allowing the principal from account A
- C. Both an identity policy in account A and a bucket policy in account B permitting the access
- D. An SCP in account B allowing the actions

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Within a single account either side is sufficient, but cross-account access requires both the caller's identity policy and the resource policy to allow it.

**Q3.** A managed service provider needs to assume a role in many customer accounts. Which condition should each customer include in the role's trust policy to prevent the confused deputy problem?

- A. `aws:SourceIp`
- B. `sts:ExternalId`
- C. `aws:PrincipalOrgID`
- D. `aws:SecureTransport`

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* A unique external ID per customer prevents one customer inducing the provider to act against another.

**Q4.** A company is building a mobile application expected to serve millions of consumer users, who need to sign in and upload files to their own prefix in an S3 bucket. Which service should manage those users?

- A. AWS IAM Identity Center
- B. AWS Directory Service
- C. IAM users, one per application user
- D. Amazon Cognito user pools with an identity pool

**Answer: D.** *Target exam: AWS Certified Solutions Architect - Associate.* Cognito is built for application users at scale; IAM Identity Center is for workforce access to AWS accounts, and creating IAM users per customer does not scale and is not the intended use.

**Q5.** A central networking team wants several application accounts to deploy resources into subnets of a VPC that the networking team manages. Which service supports this?

- A. AWS Resource Access Manager
- B. AWS Organizations service control policies
- C. AWS STS assume-role
- D. VPC peering

**Answer: A.** *Target exam: AWS Certified Solutions Architect - Associate.* RAM shares the resource itself across accounts; assume-role grants API permissions rather than the use of a subnet, and peering connects separate VPCs rather than sharing one.

**Q6.** A user has an IAM policy granting `kms:Decrypt` on a customer managed key, but decryption requests fail with access denied. What is the most likely cause?

- A. The key is in a different Region
- B. The key policy does not grant the user access or delegate access control to IAM
- C. Automatic key rotation is disabled
- D. The user lacks `s3:GetObject`

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* A KMS key policy is mandatory and is the primary access control for the key; an IAM policy alone is insufficient unless the key policy allows it.
