# Chapter 31: The Entry-Level Cloud Engineer Role

---

Parts II and III were about knowing services and designing with them. Part IV is about operating them, which is what the job consists of on a Tuesday afternoon.

The remaining chapters are hands-on: the AWS CLI across a dozen services, the SDKs in four languages, CloudShell, Terraform, and a capstone. This chapter sets up the conventions everything else depends on.

---

## 31.1 What the Job Actually Involves

Job descriptions describe architecture. The work is mostly smaller than that.

**Daily**

- Respond to tickets: access requests, quota increases, "why can this instance not reach that database."
- Check alarms and dashboards, and decide which ones matter.
- Run and verify deployments.
- Investigate a cost anomaly or a failed backup.

**Weekly**

- Patch and update, usually through Systems Manager.
- Review and merge infrastructure changes.
- Clean up resources nobody claimed.
- Update runbooks and documentation after something broke.

**Occasionally**

- Build something new, which is the part the job description described.
- Take part in an incident.
- Support an audit.

**What separates people who do this well**

- **They automate the second occurrence.** The first time a task appears, do it. The second time, script it.
- **They read errors carefully.** AWS error messages are usually specific. `AccessDenied` names the action and often the resource.
- **They verify rather than assume.** Check the Region, check the account, check what the command actually returned.
- **They clean up.** Orphaned volumes, unassociated addresses, and forgotten NAT gateways are how small accounts become expensive ones.
- **They write things down.** The runbook written after an incident is what makes the second one shorter.

---

## 31.2 Choosing an Access Method

Section 4.4 introduced the methods. This is the decision in a working context.

| Method | Development and test | Production |
| --- | --- | --- |
| Management Console | Good for exploring and reading state | Read and investigate; avoid making changes |
| AWS CLI v2 | Primary tool for one-off tasks | Yes, with a named profile and short-lived credentials |
| AWS SDKs | For application code | Yes |
| CloudShell | Convenient, nothing to install | Yes, and preferred where laptops must not hold credentials |
| Infrastructure as code | Recommended | Required for anything that must exist tomorrow |
| IAM Identity Center | Not usually needed | Required for human access |
| Systems Manager Session Manager | Useful | Required, replacing SSH and bastion hosts |
| Direct API with SigV4 | Rarely | Only through an SDK |

**The working rule**

- **Explore in the console.** It is the fastest way to understand an unfamiliar service and to read state.
- **Operate from the CLI.** Anything done more than once should be a command, then a script.
- **Build with infrastructure as code.** Anything that must exist tomorrow, be reproducible, or be reviewed belongs in a template.

**Why console changes cause problems in production.** They are not reviewed, not versioned, and not repeatable. They cause drift, so the template no longer describes reality and the next stack update fails or reverts the change. When an incident later asks what changed, there is no record. Read in the console freely; change through code.

---

## 31.3 The Credential Resolution Model

Every tool in Part IV, the CLI, every SDK, and CloudShell, resolves credentials the same way. Understanding the order saves hours of confusion about why a command used the wrong account.

**Resolution order, first match wins**

1. Command line options, such as `--profile` or explicitly passed credentials.
2. Environment variables: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_SESSION_TOKEN`.
3. The credentials file at `~/.aws/credentials`, in the profile named by `AWS_PROFILE` or `default`.
4. The config file at `~/.aws/config`, including SSO and assume-role settings.
5. Container credentials, for ECS tasks and Fargate.
6. Instance metadata, for EC2 instance profiles, using IMDSv2.

**Region resolution follows a similar order:** `--region`, then `AWS_REGION` or `AWS_DEFAULT_REGION`, then the profile's `region` setting, then the instance's Region.

**The three failures this explains**

- **The command ran against the wrong account.** An environment variable from an earlier session is overriding the profile you thought you selected. Check with `env | grep AWS` and `aws sts get-caller-identity`.
- **It works on your laptop but not on the instance.** The laptop has a profile; the instance has an instance profile with different permissions.
- **It worked yesterday.** Session credentials expired.

**`aws sts get-caller-identity` is the first command to run** whenever anything is unexpected. It returns the account ID, the user ID, and the ARN of the identity actually being used, which is the only reliable answer to "who am I right now."

**Credential types, in order of preference**

1. **IAM Identity Center**, through `aws sso login`. Short-lived, no stored secret, and the production standard.
2. **Instance or task roles**, for anything running on AWS. Nothing to store.
3. **Assumed roles** through `AssumeRole`, for cross-account access.
4. **Long-lived access keys**, only where nothing else works, rotated regularly, and never committed to a repository.

---

## 31.4 Working Conventions

These apply to every chapter that follows.

**Placeholders.** Angle-bracket values are substituted with your own. The convention used throughout Part IV:

| Placeholder | Meaning |
| --- | --- |
| `<AWS_ACCOUNT_ID>` | The 12-digit account number |
| `<REGION>` | The Region code, such as `us-east-1` |
| `<PROFILE>` | The named CLI profile |
| `<VPC_ID>`, `<SUBNET_ID>`, `<SG_ID>` | Resource identifiers from earlier commands |
| `<KEY_NAME>` | The key pair name |
| `<BUCKET>` | A globally unique bucket name |

**Naming.** Pick a scheme and hold to it, because names cannot always be changed later. A workable one is `<environment>-<application>-<resource>-<qualifier>`, giving `prod-orders-sg-web` or `dev-billing-rds-primary`. Security group names and descriptions in particular are immutable after creation.

**Tagging.** Every resource that supports tags gets at least these:

| Tag | Purpose |
| --- | --- |
| `Name` | Human-readable identifier shown in the console |
| `Environment` | `prod`, `staging`, `dev` |
| `Owner` | Team or individual accountable |
| `Project` or `CostCenter` | Cost attribution |
| `ManagedBy` | `terraform`, `cloudformation`, or `manual` |

`ManagedBy` earns its place quickly. It answers whether a resource can be safely changed by hand, and it identifies what to clean up.

**Region discipline.** Set a default Region in your profile and check it before creating anything. Resources in a Region you have forgotten about keep billing and do not appear in your usual console view. Most "the resource has disappeared" reports are a Region selector.

**Account discipline.** Run `aws sts get-caller-identity` before any command that changes something in an unfamiliar terminal. Use distinctly named profiles, and set a different console display color per account through role switching, as covered in section 7.5.1.

**Cleanup.** Every lab and every experiment ends with teardown. Beyond the obvious, check for: unattached EBS volumes, snapshots of volumes that no longer exist, unassociated Elastic IP addresses, NAT gateways, load balancers, and AMIs whose snapshots persist after deregistration.

---

## 31.5 Skills You Are Assumed to Have

Part IV assumes working familiarity with the following. Where a line is unfamiliar, learn it before the chapter that needs it.

**Shell and Linux**

- Navigating, copying, moving, and removing files; `find` and `grep`.
- Redirection and pipes, and chaining commands.
- File permissions and ownership, `chmod` and `chown`, and why `chmod 400` on a private key matters.
- Editing with `vi` or `nano` over SSH.
- `systemctl` to start, stop, enable, and check services.
- Reading logs: `journalctl`, `/var/log/`, `tail -f`.
- Package management with `dnf`, `yum`, or `apt`.
- Environment variables and shell profiles.

**Networking**

- IP addressing, CIDR notation, and subnetting, per section 9.1.
- TCP versus UDP, and what a port is.
- DNS resolution and what a record type means.
- `ping`, `curl`, `dig`, `nslookup`, `netstat` or `ss`, and `traceroute`.
- What a firewall rule expresses, and the difference between stateful and stateless.

**Tooling**

- Git: clone, branch, commit, push, pull request. Covered further in section 38.1.
- JSON and YAML, since every AWS API returns one and most templates are the other.
- A JSON processor, `jq`, which makes CLI output usable.

**A self-check.** If you can SSH to a host, find why a service failed to start from its logs, fix a permissions problem, and confirm whether a port is reachable from another machine, you have enough Linux for Part IV. If you can explain why `10.0.1.0/24` and `10.0.0.0/16` overlap, you have enough networking.

---

## 31.6 End-of-Chapter Questions

**Q1.** An engineer runs an AWS CLI command and it operates on the wrong account, despite `--profile prod` being specified in a previous command. What should be checked first?

- A. The `~/.aws/config` file region setting
- B. Environment variables, since `AWS_ACCESS_KEY_ID` and related variables take precedence over profiles, confirmed with `aws sts get-caller-identity`
- C. The instance profile attached to the host
- D. Whether the CLI needs upgrading

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* Command line options come first in the resolution chain, then environment variables, then profiles; a leftover exported variable silently overrides a profile in later commands.

**Q2.** Which credential type is preferred for an application running on an EC2 instance?

- A. Long-lived access keys in a configuration file
- B. An IAM instance profile providing automatically rotated temporary credentials
- C. Root user access keys
- D. Environment variables set at boot from a script

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* An instance profile means no credential is stored anywhere, and the SDKs and CLI retrieve and refresh it automatically from instance metadata.

**Q3.** Why are manual console changes discouraged in production environments managed by infrastructure as code?

- A. The console is slower than the CLI
- B. They cause drift, so the template no longer matches reality, and they leave no reviewable or reversible record
- C. Console changes cannot be audited by CloudTrail
- D. The console cannot create most resource types

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* CloudTrail does record console actions, but the change is still unreviewed, unversioned, and liable to be reverted or to break the next stack update.

**Q4.** Which tag most directly helps an engineer decide whether a resource can be safely modified by hand?

- A. `Name`
- B. `Environment`
- C. `ManagedBy`
- D. `CostCenter`

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* A resource tagged `ManagedBy=terraform` will have manual changes reverted on the next apply, so the tag answers the question before the change is attempted.
