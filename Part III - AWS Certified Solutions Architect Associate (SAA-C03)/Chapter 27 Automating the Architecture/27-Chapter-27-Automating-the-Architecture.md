# Chapter 27: Automating the Architecture

---

This chapter owns the design view of infrastructure as code and deployment. Section 35.1 covers CloudFormation CLI operations, and Chapter 37 teaches Terraform hands-on.

[Written to the SAA-C03 exam guide and verified against AWS documentation, as the Part III source repository ends after the storage chapter.]

---

## 27.1 Infrastructure as Code Principles

**Declarative, not imperative.** A template states the desired end state and the service works out how to reach it. A script states the steps. Declarative definitions are idempotent: applying the same template twice produces the same result, which a script rarely does.

**Version controlled.** Infrastructure in a repository gets the same treatment as application code: history, review, blame, and rollback. "Who changed the security group and why" becomes answerable.

**Reproducible.** The same definition produces the same environment. This is what makes development, test, and production genuinely comparable, and it is why the Well-Architected general design principle about testing at production scale is affordable.

**No console changes.** The moment someone edits a stack-managed resource in the console, the template stops describing reality. Drift detection finds this; discipline prevents it.

**What this enables**

- Environments created and destroyed on demand, which makes a full-scale test environment a temporary cost rather than a permanent one.
- Disaster recovery that redeploys rather than restores, because the environment definition is the recovery artifact.
- Peer review of infrastructure changes before they reach production.
- Consistent tagging, encryption, and security settings, because they are written once.

---

## 27.2 AWS CloudFormation

**Structure**

- **Template**: a JSON or YAML document describing resources.
- **Stack**: the set of resources created from a template, managed as one unit.
- **Change set**: a preview of what an update would do, including which resources would be replaced. Never update a production stack without reviewing one.
- **Stack policy**: protects specified resources from update or deletion during a stack update.

**Template sections**

| Section | Purpose |
| --- | --- |
| `Parameters` | Inputs supplied at deployment, allowing one template to serve several environments |
| `Mappings` | Static lookup tables, commonly Region to AMI ID |
| `Conditions` | Create or configure resources depending on a parameter, such as Multi-AZ only in production |
| `Resources` | The only mandatory section |
| `Outputs` | Values returned, optionally exported for other stacks to import |
| `Transform` | Macros, including `AWS::Serverless` for SAM |

**Intrinsic functions** worth knowing: `Ref` and `Fn::GetAtt` for referencing resources, `Fn::Sub` for string substitution, `Fn::ImportValue` for cross-stack references, and `Fn::If` with conditions.

**Update behavior** is the part that causes outages. Depending on the property changed, an update performs one of three things:

- **No interruption**, such as changing a tag.
- **Some interruption**, such as changing an EC2 instance type, which stops and starts it.
- **Replacement**, which creates a new resource and deletes the old one. Changing an RDS instance's `DBInstanceIdentifier` replaces the database and destroys the data.

Change sets show which of these will happen. This is why they exist.

**Deletion policy and retention**

- `DeletionPolicy: Retain` keeps a resource when the stack is deleted, appropriate for databases and buckets.
- `DeletionPolicy: Snapshot` takes a final snapshot first, supported on RDS, EBS, and a few others.
- `UpdateReplacePolicy` does the same for replacement during an update.

Without these, deleting a stack deletes its data. This is a recurring cause of real data loss.

**Nested stacks and cross-stack references**

- **Nested stacks** embed one stack in another as a resource, for reusable components such as a standard VPC. The parent owns the lifecycle.
- **Cross-stack references** use `Export` and `Fn::ImportValue` to share values between independent stacks. An exported value cannot be changed or deleted while another stack imports it, which is a durable coupling.

Prefer nested stacks for composition and exports for genuinely shared, stable values such as a VPC ID.

**StackSets** deploy one template across many accounts and Regions from a single operation, with automatic deployment to new accounts joining an organizational unit. This is the mechanism for organization-wide baselines: logging configuration, IAM roles, Config rules, and guardrails.

**Drift detection** compares actual resource configuration against the template and reports differences. Run it periodically; drift is how a stack that looks fine fails on its next update.

**Rollback.** A failed stack update rolls back to the previous state by default. `DisableRollback` preserves the failed state for investigation, at the cost of leaving the stack in `UPDATE_FAILED`.

---

## 27.3 CDK, SAM, and Terraform

All three ultimately produce infrastructure; the difference is the authoring experience and the state model.

| Tool | Language | Produces | Suits |
| --- | --- | --- | --- |
| CloudFormation | YAML or JSON | Stacks directly | AWS-only, no build step wanted, maximum stability |
| AWS CDK | TypeScript, Python, Java, C#, Go | CloudFormation templates | Teams who want loops, conditionals, and abstractions in a real language |
| AWS SAM | Simplified YAML | CloudFormation templates via a transform | Serverless applications specifically |
| Terraform | HCL | API calls, tracked in its own state file | Multi-cloud, or teams already standardized on it |

**CDK** lets you express a pattern once as a construct and reuse it, which removes the copy-paste that plain templates encourage. Its cost is a build step and a dependency on the CDK version. `cdk synth` produces the template, and `cdk diff` is the equivalent of a change set.

**SAM** adds resource types such as `AWS::Serverless::Function` that expand into several CloudFormation resources, plus a local testing experience with `sam local`. It is CloudFormation with less boilerplate for serverless.

**Terraform** differs in one important respect: it maintains its own **state file** recording what it created, whereas CloudFormation stores stack state in the service. That state file must be stored remotely and locked, or two engineers running apply concurrently will corrupt it. Chapter 37 covers this.

**Choosing.** If everything is AWS and the team is comfortable with YAML, CloudFormation is the least moving parts. If the infrastructure has repetition that YAML makes painful, CDK. If it is a serverless application, SAM or CDK. If the organization already uses Terraform, or resources outside AWS are in scope, Terraform.

---

## 27.4 Deployment Strategies

| Strategy | How it works | Downtime | Rollback | Cost |
| --- | --- | --- | --- | --- |
| In-place | Update the existing instances | Brief | Redeploy the previous version | None extra |
| Rolling | Replace instances in batches | None | Roll forward or back through batches | Small extra |
| Rolling with additional batch | Add capacity first, then replace | None | As rolling, with full capacity maintained | One batch extra |
| Immutable | Launch an entirely new set, then switch | None | Terminate the new set | Double, briefly |
| Blue/green | Run two full environments, switch traffic | None | Switch traffic back, immediately | Double, briefly |
| Canary | Send a small percentage to the new version, then increase | None | Shift traffic back | Small extra |

**Blue/green** is the strongest rollback story: the previous environment still exists and is running, so reverting is a traffic switch rather than a redeployment. Implement with Route 53 weighted records, an ALB with two target groups, CodeDeploy's blue/green mode, or two Elastic Beanstalk environments with a CNAME swap.

**Canary** limits blast radius by exposing the new version to a small share of real traffic first. On Lambda, weighted aliases do this natively, as covered in section 26.3. On containers, CodeDeploy supports canary shifting for ECS.

**Database changes are the hard part.** Application code can be rolled back; a schema migration usually cannot. The standard discipline is backward-compatible migrations deployed separately from application changes: add the new column, deploy code writing to both, backfill, deploy code reading the new column, then remove the old one. Each step is individually reversible.

---

## 27.5 CI/CD on AWS

| Service | Role |
| --- | --- |
| AWS CodePipeline | Orchestrates the stages: source, build, test, approve, deploy |
| AWS CodeBuild | Runs builds and tests in managed containers, defined by a buildspec file |
| AWS CodeDeploy | Deploys to EC2, on-premises, Lambda, and ECS, with in-place and blue/green modes |
| AWS CodeArtifact | Private package repository |
| AWS CodeCommit | Managed Git repositories, returned to general availability in November 2025 as noted in section 14.4 |
| Amazon CodeCatalyst | Unified source, build, deploy, and project tracking |

**Pipeline design**

- **Source** triggers on a commit to a branch.
- **Build** compiles, runs unit tests, and produces an artifact, ideally once, then promotes the same artifact through environments.
- **Test** deploys to a non-production environment and runs integration tests.
- **Manual approval** gates production where the organization requires it.
- **Deploy** uses the chosen strategy.

**Build the artifact once.** Rebuilding per environment means production runs something that was never tested. Promote the same artifact and vary only configuration.

**Where infrastructure fits.** Infrastructure changes belong in the pipeline alongside application changes, deployed by CloudFormation or Terraform from the same repository or a linked one. A pipeline that deploys code onto infrastructure someone built by hand has automated only half the problem.

**Least privilege for pipelines.** A deployment role often holds broad permissions and is therefore a target. Scope it to what it deploys, use separate roles per environment, and require cross-account assume-role for production rather than giving one account permission everywhere.

---

## 27.6 Operational Automation

**AWS Systems Manager** is where most operational automation lives.

- **Automation runbooks** execute multi-step operational procedures, such as patching, AMI creation, or remediating a misconfiguration.
- **Run Command** executes commands across a fleet without SSH.
- **Session Manager** gives shell access with no open inbound ports, no bastion host, and full session logging. This is the modern replacement for a bastion, and the answer whenever a question asks for instance access without exposing SSH.
- **Patch Manager** applies patches on a schedule with maintenance windows.
- **State Manager** keeps instances in a defined configuration.
- **Parameter Store** holds configuration and secrets, as covered in section 17.7.3.

**Event-driven remediation.** EventBridge rules match an event and invoke a target that fixes it. Examples: a Config rule detects an unencrypted volume and triggers an Automation runbook; GuardDuty raises a finding and a Lambda function isolates the instance by replacing its security group; a CloudTrail event shows a security group opened to the world and a runbook reverts it.

**AWS Config auto-remediation** attaches a remediation action directly to a rule, which is simpler than wiring EventBridge for the same purpose.

**The judgment involved.** Automated remediation that reverts a change someone made deliberately, during an incident, is worse than an alert. Automate remediation for things that are unambiguously wrong, and alert for things that require a decision.

---

## 27.7 End-of-Chapter Questions

**Q1.** An engineer updates a CloudFormation stack and the RDS database is replaced, destroying its data. What should have been done to prevent this?

- A. Enabled termination protection on the stack
- B. Reviewed a change set before applying the update, which would have shown the replacement
- C. Enabled drift detection
- D. Used a nested stack for the database

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Change sets show exactly which resources will be replaced; a `DeletionPolicy` and `UpdateReplacePolicy` of `Snapshot` or `Retain` would also have preserved the data.

**Q2.** A company must deploy a standard set of IAM roles and AWS Config rules to every account in its organization, including accounts created in future. What should be used?

- A. A CloudFormation stack in each account
- B. CloudFormation StackSets with automatic deployment to the organizational unit
- C. Nested stacks
- D. A Terraform module run manually per account

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* StackSets deploy across accounts and Regions from one operation and can enroll new accounts joining an OU automatically.

**Q3.** A deployment must allow immediate rollback to the previous version with no redeployment, and the budget permits running duplicate capacity briefly. Which strategy fits?

- A. In-place deployment
- B. Rolling deployment
- C. Blue/green deployment
- D. Canary deployment

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Blue/green keeps the previous environment running, so rollback is a traffic switch rather than a redeployment.

**Q4.** Engineers need shell access to instances in private subnets for troubleshooting, with no inbound ports open, no bastion host, and full audit logging. What should be used?

- A. A bastion host in a public subnet with SSH restricted to the office IP
- B. AWS Systems Manager Session Manager
- C. EC2 Instance Connect over the internet gateway
- D. A Site-to-Site VPN with SSH access

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Session Manager connects outbound through the SSM agent, requires no inbound rules or bastion, and logs sessions to CloudWatch Logs or S3.

**Q5.** A CloudFormation stack update fails because a resource no longer matches the template, having been modified in the console. Which feature identifies this class of problem before an update is attempted?

- A. Change sets
- B. Drift detection
- C. Stack policies
- D. Rollback triggers

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Drift detection compares live configuration against the template and reports resources that have been changed outside CloudFormation.

**Q6.** A pipeline rebuilds the application artifact separately for each environment. What risk does this introduce?

- A. Higher build costs only
- B. Production may run a build that was never tested, since each environment gets a different artifact
- C. Deployments become slower
- D. CodeBuild cannot deploy to multiple environments

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Building once and promoting the same artifact is what guarantees the tested thing is the deployed thing; rebuilding per environment breaks that guarantee.
