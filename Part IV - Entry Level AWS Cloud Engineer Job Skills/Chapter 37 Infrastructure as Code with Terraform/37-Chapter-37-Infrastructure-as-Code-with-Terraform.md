# Chapter 37: Infrastructure as Code with Terraform

---

Chapter 27 covered why infrastructure as code matters and where Terraform sits among the options. This chapter is hands-on: install it, write a first project, learn the workflow, then variables, outputs, state, modules, and remote state.

**Terraform or CloudFormation.** Both are covered in this course because both are common. Terraform is cloud-agnostic, uses its own state file, and has a large module registry. CloudFormation is AWS-native and stores state in the service. The concepts transfer; the syntax and state model differ. Section 27.3 covers the choice.

**Environment note.** The commands assume credentials supplied through environment variables, which is how a temporary lab environment such as AWS Academy provides them. Confirm identity before every session with `aws sts get-caller-identity`, since these credentials expire.

---

## 37.1 Installation

### 37.1.1 Prerequisites

- The AWS CLI v2, installed and configured per Chapter 32.
- Credentials that work, confirmed with `aws sts get-caller-identity`.

![Retrieving temporary credentials for the lab session](https://github.com/user-attachments/assets/68bbc6e8-76b5-4265-8791-26b7cf6d3a91)

If you installed the CLI while following this chapter for the first time, verify it now:

```bash
aws --version
```

![AWS CLI installed and reporting its version](https://github.com/user-attachments/assets/890784ae-1e09-45d5-8cd4-aff11365ccb2)

```bash
aws sts get-caller-identity
```

![aws sts get-caller-identity confirming the active identity](https://github.com/user-attachments/assets/c872029a-defd-47dd-88de-3d59cae5001f)

### 37.1.2 Install Terraform

**Linux, Debian or Ubuntu**

```bash
wget -O - https://apt.releases.hashicorp.com/gpg | \
  sudo gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg

echo "deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] \
  https://apt.releases.hashicorp.com $(lsb_release -cs) main" | \
  sudo tee /etc/apt/sources.list.d/hashicorp.list

sudo apt update && sudo apt install -y terraform
```

**Linux, RHEL, CentOS, or Amazon Linux**

```bash
sudo dnf install -y dnf-plugins-core
sudo dnf config-manager --add-repo https://rpm.releases.hashicorp.com/AmazonLinux/hashicorp.repo
sudo dnf install -y terraform
```

**macOS**

```bash
brew tap hashicorp/tap
brew install hashicorp/tap/terraform
```

**Windows**

```powershell
winget install HashiCorp.Terraform
```

**Verify**

```bash
terraform version
```

Enable tab completion, which saves considerable typing:

```bash
terraform -install-autocomplete
```

---

## 37.2 Your First Project

### 37.2.1 Project Structure

Terraform reads every `.tf` file in the working directory and merges them. Splitting by purpose is a convention, not a requirement.

```
terraform-aws-lab/
  terraform.tf      # Terraform and provider version constraints
  main.tf           # Resources and data sources
  variables.tf      # Input variable declarations
  outputs.tf        # Output value declarations
  terraform.tfvars  # Variable values (never committed)
```

### 37.2.2 Provider Configuration

`terraform.tf`:

```hcl
terraform {
  required_version = ">= 1.5.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
  }
}

provider "aws" {
  region = var.aws_region
  # Credentials resolve from the environment, never hardcoded here.
}
```

The AWS provider version 6 reached general availability in 2025. Pinning `~> 6.0` accepts any 6.x release and excludes version 7, which protects against a major-version breaking change arriving unannounced.

Terraform resolves credentials in the same order as the CLI: environment variables first, then `~/.aws/credentials`, then an instance profile. This is why the provider block needs no credentials.

### 37.2.3 A Data Source for the AMI

`main.tf`:

```hcl
data "aws_ami" "amazon_linux" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["al2023-ami-2023*-kernel-6.1-x86_64"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
}
```

A data source reads existing information rather than creating anything. AMI IDs are Region-specific and change with each release, so resolving the latest one dynamically is always correct where hardcoding an ID is not. Reference the result as `data.aws_ami.amazon_linux.id`.

Use Amazon Linux 2023. Amazon Linux 2 reaches end of support on June 30, 2026, so `amzn2-ami-*` filters do not belong in new configurations.

### 37.2.4 A Security Group and an Instance

Continuing `main.tf`:

```hcl
resource "aws_security_group" "web_sg" {
  name        = "${var.project_name}-web-sg"
  description = "Allow HTTP and SSH"

  ingress {
    description = "SSH"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = [var.ssh_cidr]
  }

  ingress {
    description = "HTTP"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name        = "${var.project_name}-web-sg"
    Environment = var.environment
    ManagedBy   = "Terraform"
  }
}

resource "aws_instance" "web" {
  ami                    = data.aws_ami.amazon_linux.id
  instance_type          = var.instance_type
  vpc_security_group_ids = [aws_security_group.web_sg.id]

  user_data = <<-EOF
    #!/bin/bash
    dnf update -y
    dnf install -y httpd
    systemctl enable --now httpd
    echo "<h1>Deployed by Terraform on $(hostname -f)</h1>" > /var/www/html/index.html
  EOF

  tags = {
    Name        = "${var.project_name}-web"
    Environment = var.environment
    ManagedBy   = "Terraform"
  }
}
```

Two things worth noting. First, the source material opened SSH to `0.0.0.0/0`. This version routes SSH through a `var.ssh_cidr` variable so it can be restricted to your own address, and the default in section 37.3 should be your IP rather than the whole internet. Second, `aws_instance.web` references `aws_security_group.web_sg.id`, and Terraform reads that reference as a dependency and creates the security group first, with no explicit ordering needed. This implicit dependency graph is central to how Terraform works.

---

## 37.3 The Workflow

Five commands do almost everything. Confirm credentials first, since they expire in a lab environment:

```bash
aws sts get-caller-identity
```

### 37.3.1 init

```bash
terraform init
```

Downloads the providers and modules and prepares the working directory. Run it once at the start and again whenever providers or modules change. It creates the `.terraform/` directory and the `.terraform.lock.hcl` lock file, which pins provider versions and should be committed.

### 37.3.2 validate

```bash
terraform validate
```

Checks syntax and internal consistency. It does not contact AWS and does not check whether the resources can actually be created, only that the configuration is well-formed.

### 37.3.3 plan

```bash
terraform plan
```

Shows what Terraform would change to make reality match the configuration, without making any change. Each line is prefixed:

- `+` create
- `-` destroy
- `~` update in place
- `-/+` destroy and recreate, which means downtime

**Always read the plan before applying.** The `-/+` lines are the ones that cause outages and data loss, and the plan is the only warning you get.

### 37.3.4 apply

```bash
terraform apply
```

Shows the plan again and waits for you to type `yes`. In automation, `terraform apply -auto-approve` skips the prompt, which is appropriate in a reviewed pipeline and dangerous at a keyboard.

### 37.3.5 destroy

```bash
terraform destroy
```

Removes everything in the configuration. It also shows a plan and requires confirmation. In a lab charged by the hour, this is how a session ends, and forgetting it is how a lab becomes a bill.

---

## 37.4 Variables

`variables.tf` declares inputs, which is what makes a configuration reusable across environments.

```hcl
variable "aws_region" {
  description = "AWS region"
  type        = string
  default     = "us-east-1"
}

variable "project_name" {
  description = "Prefix for all resource names"
  type        = string
  default     = "terraform-lab"
}

variable "environment" {
  description = "Environment name"
  type        = string
  default     = "dev"

  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "Must be dev, staging, or prod."
  }
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t3.micro"
}

variable "ssh_cidr" {
  description = "CIDR permitted to reach SSH; set to your own address"
  type        = string
  default     = "0.0.0.0/0"
}
```

The `validation` block rejects an invalid value at plan time with a clear message, rather than failing partway through an apply. Change the `ssh_cidr` default to your own `x.x.x.x/32` before using this.

**Variable types**

| Type | Example |
| --- | --- |
| `string` | `"t3.micro"` |
| `number` | `3` |
| `bool` | `true` |
| `list(string)` | `["us-east-1a", "us-east-1b"]` |
| `map(string)` | `{ env = "dev" }` |

**Setting values, lowest to highest precedence**

1. The `default` in the declaration.
2. A `terraform.tfvars` file, loaded automatically.
3. Any `*.auto.tfvars` file.
4. A `-var-file` flag.
5. A `-var` flag on the command line.
6. A `TF_VAR_` environment variable.

`terraform.tfvars`:

```hcl
aws_region    = "us-east-1"
project_name  = "my-lab"
environment   = "dev"
instance_type = "t3.micro"
ssh_cidr      = "203.0.113.10/32"
```

```bash
export TF_VAR_environment=dev
terraform apply -var="instance_type=t3.small"
```

---

## 37.5 Outputs

`outputs.tf` returns values after apply, for display or for another tool to consume.

```hcl
output "instance_id" {
  description = "EC2 instance ID"
  value       = aws_instance.web.id
}

output "instance_public_ip" {
  description = "EC2 public IP"
  value       = aws_instance.web.public_ip
}

output "web_url" {
  description = "Web server URL"
  value       = "http://${aws_instance.web.public_ip}"
}
```

```bash
terraform output                      # all outputs
terraform output instance_public_ip   # one value
terraform output -json                # JSON, for scripting
```

Mark anything sensitive so it is not printed to the terminal:

```hcl
output "db_password" {
  value     = aws_db_instance.main.password
  sensitive = true
}
```

A sensitive output is still stored in state in plaintext, so this hides it from the console rather than protecting it fully. Protecting the state itself is section 37.8.

---

## 37.6 Managing Changes

Terraform compares the configuration against state and acts only on the difference.

**In-place update.** Changing the instance type produces:

```
~ resource "aws_instance" "web" {
    ~ instance_type = "t3.micro" -> "t3.small"
  }
Plan: 0 to add, 1 to change, 0 to destroy.
```

**Forced replacement.** Some changes, such as a new AMI, cannot be made in place:

```
-/+ resource "aws_instance" "web" {
      ~ ami = "ami-old" -> "ami-new"   # forces replacement
    }
Plan: 1 to add, 0 to change, 1 to destroy.
```

The `-/+` and the `forces replacement` note together mean the instance is destroyed and recreated, which is downtime and loses anything on the instance store. Read for these before applying.

**Tag changes are always in place** and never cause a restart, which is why the `ManagedBy = "Terraform"` tagging convention costs nothing to maintain.

---

## 37.7 State

`terraform.tfstate` maps the configuration to real AWS resources and is Terraform's source of truth. It records what exists, so a plan can compute the difference.

**Inspecting state**

```bash
terraform state list                      # every tracked resource
terraform state show aws_instance.web     # full attributes of one
terraform show -json                      # entire state as JSON
```

**Manipulating state**

```bash
terraform state rm aws_instance.web            # stop tracking; resource stays in AWS
terraform import aws_instance.web i-0abc123    # start tracking an existing resource
terraform state mv aws_instance.web aws_instance.app   # rename within state
```

`import` is how a manually created resource is brought under management. `state rm` is how a resource is handed off to something else without destroying it. Both edit state without touching infrastructure.

**Why local state is a problem.** A local `terraform.tfstate` lives on one machine. It is lost if that machine is wiped, which in a lab environment that resets between sessions means Terraform forgets what it created and `destroy` can no longer clean up. Two people running apply against the same local state corrupt it. The fix is remote state, in section 37.8.

**Never edit the state file by hand.** Use the `state` subcommands. A hand-edited state file that no longer matches its own internal checksums produces errors that are difficult to recover from.

---

## 37.8 Remote State

Remote state solves three problems at once: it survives a machine reset, it is shared across a team, and it can be locked so two applies do not run at the same time.

### 37.8.1 Bootstrap the Backend

The backend bucket must exist before Terraform can use it, so create it once with the CLI.

```bash
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
BUCKET="terraform-state-${ACCOUNT_ID}"

aws s3api create-bucket --bucket "$BUCKET" --region us-east-1

aws s3api put-bucket-versioning --bucket "$BUCKET" \
  --versioning-configuration Status=Enabled

aws s3api put-bucket-encryption --bucket "$BUCKET" \
  --server-side-encryption-configuration \
  '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"}}]}'

aws s3api put-public-access-block --bucket "$BUCKET" \
  --public-access-block-configuration \
  "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"
```

Versioning matters here beyond general good practice: it lets you recover a previous state if an apply goes wrong, and it is required for safe native state locking.

### 37.8.2 Configure the Backend

Add a `backend` block to `terraform.tf`, substituting your bucket name:

```hcl
terraform {
  required_version = ">= 1.11.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
  }

  backend "s3" {
    bucket       = "terraform-state-123456789012"
    key          = "terraform-aws-lab/terraform.tfstate"
    region       = "us-east-1"
    encrypt      = true
    use_lockfile = true
  }
}
```

`use_lockfile = true` enables **native S3 state locking**, which is the current mechanism. Terraform writes a small `.tflock` object next to the state file using a conditional write, and a second concurrent apply fails with a lock error rather than corrupting state.

**On the older DynamoDB method.** Study material written before Terraform 1.10 uses a separate DynamoDB table with a `dynamodb_table` argument for locking. As of the current AWS provider documentation, DynamoDB-based locking is deprecated and will be removed in a future version, replaced by the lock file shown above. If you inherit a configuration using `dynamodb_table`, it still works, and you can set both arguments during migration, but new configurations should use `use_lockfile` and require Terraform 1.11 or later. This is a change your source material predates.

### 37.8.3 Migrate

```bash
terraform init -migrate-state
```

Terraform detects the new backend, offers to copy the existing local state into S3, and asks for confirmation. After this, the state lives in S3, each new session needs only fresh credentials exported, and the lock file prevents concurrent corruption.

---

## 37.9 Registry Modules

The Terraform Registry hosts reusable modules. The official AWS VPC module provisions a complete network in about twenty lines.

```hcl
data "aws_availability_zones" "available" {
  state = "available"
}

module "vpc" {
  source  = "terraform-aws-modules/vpc/aws"
  version = "~> 6.0"

  name = "${var.project_name}-vpc"
  cidr = var.vpc_cidr

  azs             = slice(data.aws_availability_zones.available.names, 0, 2)
  public_subnets  = var.public_subnet_cidrs
  private_subnets = var.private_subnet_cidrs

  enable_nat_gateway   = true
  single_nat_gateway   = true
  enable_dns_hostnames = true

  tags = {
    ManagedBy = "Terraform"
  }
}
```

Corresponding variables:

```hcl
variable "vpc_cidr" {
  type    = string
  default = "10.0.0.0/16"
}

variable "public_subnet_cidrs" {
  type    = list(string)
  default = ["10.0.1.0/24", "10.0.2.0/24"]
}

variable "private_subnet_cidrs" {
  type    = list(string)
  default = ["10.0.10.0/24", "10.0.11.0/24"]
}
```

`single_nat_gateway = true` provisions one NAT gateway rather than one per zone, which halves or thirds the NAT cost in a lab at the price of the cross-zone dependency discussed in section 21.3. For production, set it to `false`.

**A NAT gateway costs roughly $0.045 per hour in us-east-1.** A four-hour session is about eighteen cents; a gateway left running for a month is around thirty-three dollars. This is the resource that turns a forgotten lab into a real bill, which is why `terraform destroy` at the end of every session matters.

**Place the instance in the module's VPC** by referencing the module's outputs:

```hcl
resource "aws_instance" "web" {
  ami                         = data.aws_ami.amazon_linux.id
  instance_type               = var.instance_type
  subnet_id                   = module.vpc.public_subnets[0]
  vpc_security_group_ids      = [aws_security_group.web_sg.id]
  associate_public_ip_address = true
  # ...
}
```

A module exposes chosen values as outputs, referenced as `module.vpc.vpc_id` or `module.vpc.public_subnets`. After adding any module, run `terraform init` again to download it before `plan`.

---

## 37.10 Verification, Practices, and Troubleshooting

**Verify in the console** after apply, or from the CLI:

```bash
terraform output web_url
curl "$(terraform output -raw web_url)"
```

**Git hygiene.** Commit the configuration; never commit state or secrets.

```gitignore
*.tfstate
*.tfstate.*
.terraform/
*.tfvars
!example.tfvars
*.tfplan
```

| File | Commit |
| --- | --- |
| `*.tf` | Yes |
| `.terraform.lock.hcl` | Yes, it pins provider versions |
| `terraform.tfstate` | No, use remote state |
| `.terraform/` | No, regenerated by init |
| `terraform.tfvars` | No, may contain secrets |

**Credential security.** Never write credentials into a provider block. Let them resolve from the environment, and add `*.tfvars` to `.gitignore` so a variable file holding a secret is never committed.

**Common problems**

| Symptom | Cause and fix |
| --- | --- |
| `NoCredentialProviders` or an expired token | Lab credentials expired; re-export them and confirm with `aws sts get-caller-identity` |
| `Error acquiring the state lock` | Another run holds the lock, or a previous run crashed. Confirm nothing else is running, then `terraform force-unlock <LOCK_ID>` |
| Plan shows changes you did not make | Something changed the resource outside Terraform. The plan is reconciling drift; decide whether to apply or to fix the configuration |
| `Error: creating ... UnauthorizedOperation` | The identity lacks the permission for that resource |
| A resource fails to destroy | A dependency outside Terraform, such as an S3 bucket that is not empty, is blocking it |

**Debug logging** when a failure is opaque:

```bash
export TF_LOG=DEBUG          # TRACE, DEBUG, INFO, WARN, ERROR
terraform apply
unset TF_LOG
```

**Session-end cleanup.** In a lab, destroy the expensive resources every time:

```bash
terraform destroy
```

Confirm in the console that the EC2 instance, any load balancer, the NAT gateway, and any RDS instance are gone. If you are using remote state, the S3 bucket and its lock objects are intentionally kept, since they hold the state for next session.

---

## 37.11 End-of-Chapter Questions

**Q1.** A `terraform plan` shows `-/+` next to a resource with a `forces replacement` note. What does applying it do?

- A. Update the resource in place with no disruption
- B. Destroy the existing resource and create a new one, causing downtime
- C. Create a second resource alongside the first
- D. Nothing, because the plan is read-only

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* `-/+` means destroy and recreate, which is why plans must be read before applying.

**Q2.** Why does a locally stored `terraform.tfstate` fail in an environment that resets between sessions?

- A. Terraform cannot read local files
- B. The state is lost on reset, so Terraform loses track of what it created and cannot manage or destroy it
- C. Local state is encrypted and unreadable
- D. Local state does not record resource IDs

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* State is Terraform's record of reality; losing it orphans the resources, which is why remote state in S3 is used.

**Q3.** In a current Terraform S3 backend, which argument enables state locking without a separate DynamoDB table?

- A. `dynamodb_table`
- B. `enable_locking`
- C. `use_lockfile`
- D. `lock = true`

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Native S3 locking through `use_lockfile` is the current mechanism; DynamoDB-based locking is deprecated.

**Q4.** A Terraform configuration references `data.aws_ami.amazon_linux.id` for an instance's AMI rather than a hardcoded ID. What is the benefit?

- A. It is faster to apply
- B. The data source resolves the correct, latest AMI for the active Region, which a hardcoded ID cannot
- C. It avoids the need for a provider block
- D. It encrypts the AMI

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* AMI IDs differ per Region and change with each release, so a data source keeps the configuration correct and portable.

**Q5.** An engineer needs to bring an EC2 instance that was created manually under Terraform management, without destroying it. Which command does this?

- A. `terraform apply`
- B. `terraform state rm`
- C. `terraform import`
- D. `terraform refresh`

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* `import` records an existing resource in state so Terraform manages it going forward, without recreating it.

**Q6.** Which files should be committed to version control for a Terraform project?

- A. Everything, including `terraform.tfstate`
- B. The `.tf` files and `.terraform.lock.hcl`, but not state or `.tfvars`
- C. Only the `.tf` files
- D. The `.terraform/` directory and the state file

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Configuration and the provider lock file are committed; state may hold secrets and belongs in a remote backend, and `.tfvars` may contain secrets.
