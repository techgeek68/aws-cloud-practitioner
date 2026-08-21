# Chapter 38: Working Practices and Capstone

---

The final chapter covers the practices that separate someone who can run commands from someone who can be trusted with production, then a capstone that builds a complete highly available application from code, and closes with interview and portfolio readiness.

[Written to the job-role skills for an entry-level cloud engineer. Verified against AWS and tooling documentation where version-dependent.]

---

## 38.1 Git for Infrastructure Work

Infrastructure is code, so it lives in version control and follows the same discipline.

**The core loop**

```bash
git clone <REPOSITORY_URL>
git checkout -b feature/add-monitoring

# make changes

git add .
git commit -m "Add CloudWatch alarms for the web tier"
git push -u origin feature/add-monitoring
# then open a pull request
```

**Commit messages that help.** State what changed and why, not what the diff already shows. "Increase RDS backup retention to 14 days for compliance" is useful; "update main.tf" is not. The person reading it in six months is usually you.

**Branch, do not commit to main.** Work on a branch, open a pull request, get it reviewed, and merge. For infrastructure this matters more than for application code, because a bad merge to main can be a bad merge to production.

**What never goes in a repository**

- Credentials of any kind, including in a `.tfvars` or `.env` file. Add them to `.gitignore` before the first commit, not after.
- Terraform state, which may contain secrets in plaintext.
- Private keys, certificates, and connection strings.

If a secret is ever committed, rotating it is mandatory. Removing it from the latest commit does not remove it from history, and history is what an attacker reads. Assume anything ever committed is compromised.

**Reviewing an infrastructure pull request.** Read the plan, not just the code. A one-line change to an instance identifier can be a database replacement. Attaching the `terraform plan` output to the pull request is what makes the review meaningful.

---

## 38.2 Documentation and Runbooks

**What to document, and where**

- **README** in every repository: what this is, how to deploy it, how to tear it down, and who owns it.
- **Architecture notes**: a diagram and the reasoning behind the non-obvious decisions. The diagram shows what; the notes explain why, which is what a diagram cannot.
- **Runbooks**: step-by-step procedures for operational tasks and incidents.

**A runbook that works** answers, in order: how you know the problem is happening, how to confirm it, the steps to resolve it, how to verify the resolution, and who to escalate to if it does not work. It is written for someone tired and under pressure who did not build the system, and it is specific enough to follow without thinking. "Restart the service" is not a runbook; the exact command, on which host, and how to confirm it worked, is.

**Write it after the incident, while it is fresh.** The best time to write the runbook for a failure is right after resolving it the first time. The knowledge is never more complete than in that hour, and never recovered as cheaply later.

**Keep documentation next to what it describes.** Documentation in a separate wiki drifts from the code because changing the code and changing the wiki are two actions, and the second gets skipped. A README in the repository is changed in the same pull request as the thing it documents.

---

## 38.3 A Troubleshooting Method

A repeatable method beats intuition under pressure.

1. **Establish what changed.** Most incidents follow a change: a deployment, a configuration edit, an expired credential, a crossed threshold. CloudTrail and the deployment history answer this first.
2. **Confirm the symptom.** Reproduce it, and know precisely what "broken" means. "The site is down" and "the API returns 503 for authenticated users only" lead to different investigations.
3. **Localize the layer.** Is it DNS, network, the load balancer, the instance, the application, or the database. Work outside in, and use the tools that isolate each layer: Reachability Analyzer for the network, flow logs for whether packets arrive, the load balancer's target health, the application logs, the database metrics.
4. **Form one hypothesis and test it.** Change one thing, observe, and revert if it did not help. Changing several things at once means not knowing which fixed it, or which made it worse.
5. **Fix, verify, and record.** Confirm the symptom is gone from the user's perspective, not just that a metric recovered, and write the runbook entry.

**The bias to resist** is fixing the first plausible cause without confirming it is the actual one. A method that confirms before acting is slower on the easy cases and far faster on the hard ones.

**AWS-specific first checks**

- `aws sts get-caller-identity`, because a surprising number of problems are the wrong account or Region.
- CloudTrail for what changed and who changed it.
- The service health dashboard, to rule out an AWS-side event before spending an hour on your own configuration.
- The relevant CloudWatch metrics and logs for the affected component.

---

## 38.4 On-Call Basics

An entry-level engineer joins a rotation. What matters at the start:

- **Acknowledge quickly, resolve carefully.** Acknowledging says a human is looking. It does not start a countdown to a reckless change.
- **Follow the runbook if one exists.** Improvising during an incident is how a small problem becomes a large one.
- **Communicate.** A short status update to the affected people is worth more than they expect and costs a minute. Silence during an incident is its own problem.
- **Mitigate before diagnosing.** Restoring service, by rolling back or failing over, comes before understanding the root cause. The investigation happens after the users are served.
- **Escalate without shame.** Escalating a problem beyond your depth is the correct action, not a failure. The failure is sitting on it while it grows.
- **Blameless postmortems.** After a significant incident, the team writes up what happened, why, and what will prevent it, focused on the system rather than the person. The goal is a system that does not allow the mistake, not a person who promises to be more careful.

**Alert hygiene** is part of on-call health. An alert that fires without requiring action trains people to ignore alerts, which is how the one that mattered gets missed. Every alert should be actionable or deleted, per section 23.7.

---

## 38.5 Capstone: A Highly Available Web Application from Code

This is the course's final exercise. It builds a complete, resilient, multi-tier application entirely from Terraform, exercising nearly everything in Parts III and IV. Nothing is created by hand.

**Cost warning.** This runs a NAT gateway, an Application Load Balancer, and a Multi-AZ RDS instance, none free tier eligible. Expect a few dollars for a short session, and destroy everything at the end.

### 38.5.1 Target Architecture

```
                     Route 53 / users
                          |
                 Application Load Balancer
                  (public subnets, 2 AZs)
                          |
              +-----------+-----------+
              |                       |
        Auto Scaling group across 2 private subnets
         (web instances, min 2, max 6)
              |                       |
              +-----------+-----------+
                          |
                 RDS MySQL, Multi-AZ
                (private subnets, 2 AZs)

  State: S3 backend with native locking
  Egress: one NAT gateway (lab) or one per AZ (production)
```

**Requirements**

- A VPC across two Availability Zones, with public and private subnets in each, from the registry VPC module.
- An Application Load Balancer in the public subnets.
- An Auto Scaling group of web instances in the private subnets, minimum two so one exists per zone.
- A Multi-AZ RDS MySQL instance in the private subnets.
- Security groups that tier access: the ALB from the internet, the instances from the ALB only, the database from the instances only.
- Remote state in S3 with native locking, per section 37.8.
- CloudWatch alarms on the web tier and the database.
- Every resource tagged, and a working teardown.

### 38.5.2 File Structure

```
capstone/
  terraform.tf        # provider and S3 backend
  variables.tf        # all inputs
  network.tf          # VPC module, data sources
  security.tf         # three security groups
  compute.tf          # launch template, ASG, ALB, target group
  database.tf         # RDS subnet group and instance
  monitoring.tf       # CloudWatch alarms and SNS
  outputs.tf          # ALB DNS name, database endpoint
  terraform.tfvars    # values, gitignored
```

### 38.5.3 Security Groups, the Tiered Core

`security.tf`. This is the heart of the design, and each group references the one in front of it rather than a CIDR range, so the tiers stay isolated as instances come and go.

```hcl
resource "aws_security_group" "alb" {
  name        = "${var.project_name}-alb-sg"
  description = "Internet to ALB"
  vpc_id      = module.vpc.vpc_id

  ingress {
    description = "HTTP from anywhere"
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

  tags = local.tags
}

resource "aws_security_group" "web" {
  name        = "${var.project_name}-web-sg"
  description = "ALB to web instances"
  vpc_id      = module.vpc.vpc_id

  ingress {
    description     = "HTTP from the ALB only"
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = local.tags
}

resource "aws_security_group" "db" {
  name        = "${var.project_name}-db-sg"
  description = "Web instances to database"
  vpc_id      = module.vpc.vpc_id

  ingress {
    description     = "MySQL from the web tier only"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.web.id]
  }

  tags = local.tags
}
```

No instance in the web tier is reachable except through the ALB, and the database is reachable only from the web tier, whatever addresses the instances happen to have. `local.tags` is a common tag map defined once in `variables.tf`.

### 38.5.4 Compute

`compute.tf`, the launch template, target group, load balancer, Auto Scaling group, and a target-tracking policy.

```hcl
resource "aws_launch_template" "web" {
  name_prefix   = "${var.project_name}-"
  image_id      = data.aws_ami.amazon_linux.id
  instance_type = var.instance_type

  vpc_security_group_ids = [aws_security_group.web.id]

  user_data = base64encode(<<-EOF
    #!/bin/bash
    dnf install -y httpd
    systemctl enable --now httpd
    echo "<h1>$(hostname -f)</h1>" > /var/www/html/index.html
    echo "OK" > /var/www/html/health
  EOF
  )

  tag_specifications {
    resource_type = "instance"
    tags          = local.tags
  }
}

resource "aws_lb" "web" {
  name               = "${var.project_name}-alb"
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb.id]
  subnets            = module.vpc.public_subnets
  tags               = local.tags
}

resource "aws_lb_target_group" "web" {
  name     = "${var.project_name}-tg"
  port     = 80
  protocol = "HTTP"
  vpc_id   = module.vpc.vpc_id

  health_check {
    path                = "/health"
    healthy_threshold   = 2
    unhealthy_threshold = 2
    interval            = 15
    timeout             = 5
    matcher             = "200"
  }

  tags = local.tags
}

resource "aws_lb_listener" "web" {
  load_balancer_arn = aws_lb.web.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.web.arn
  }
}

resource "aws_autoscaling_group" "web" {
  name                = "${var.project_name}-asg"
  vpc_zone_identifier = module.vpc.private_subnets
  target_group_arns   = [aws_lb_target_group.web.arn]
  health_check_type   = "ELB"
  health_check_grace_period = 300

  min_size         = 2
  max_size         = 6
  desired_capacity = 2

  launch_template {
    id      = aws_launch_template.web.id
    version = "$Latest"
  }

  tag {
    key                 = "Name"
    value               = "${var.project_name}-web"
    propagate_at_launch = true
  }
}

resource "aws_autoscaling_policy" "cpu" {
  name                   = "${var.project_name}-cpu-tracking"
  autoscaling_group_name = aws_autoscaling_group.web.name
  policy_type            = "TargetTrackingScaling"

  target_tracking_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ASGAverageCPUUtilization"
    }
    target_value = 60.0
  }
}
```

Two details carry the resilience. `health_check_type = "ELB"` makes the group replace instances the load balancer considers unhealthy, not merely those that failed an EC2 status check, per section 23.3. The `/health` endpoint is a shallow check confirming the server responds, not a deep check that would fail the whole fleet on a database blip.

### 38.5.5 Database and Monitoring

`database.tf` creates a DB subnet group across the private subnets and a Multi-AZ MySQL instance with `--manage-master-user-password` behavior through `manage_master_user_password = true`, so the credential lands in Secrets Manager rather than in the configuration. `monitoring.tf` adds an SNS topic, a CPU alarm on the Auto Scaling group, and alarms on RDS CPU and free storage space, following section 34.3.

The full contents of these two files follow the same patterns already shown and are left as the exercise. The requirement is that the database is Multi-AZ, is in the private subnets, is reachable only through the `db` security group, and has no password in any committed file.

### 38.5.6 Deploy, Validate, Tear Down

```bash
aws sts get-caller-identity
terraform init
terraform validate
terraform plan
terraform apply
```

**Validation checklist**

- `terraform output alb_dns_name` returns a hostname.
- Opening that hostname in a browser returns a page naming an instance.
- Refreshing several times shows the instance name change, proving the load balancer distributes across the group.
- The target group shows two healthy targets in two Availability Zones.
- Terminating one instance in the console causes the Auto Scaling group to launch a replacement, and the site stays up throughout.
- The RDS instance shows Multi-AZ as yes.
- No security group allows the database from anything but the web tier.
- No password appears in any file in the repository.
- The state file is in S3, confirmed with `aws s3 ls s3://<BUCKET>/`.

**Teardown**

```bash
terraform destroy
```

Then confirm in the console that the instances, the load balancer, the NAT gateway, and the RDS instance are gone, and that no snapshot or unassociated address was left behind. If using remote state, the S3 bucket and its lock objects are kept deliberately.

**What this proves.** Someone who can build, validate, and tear this down from code has demonstrated the core of the entry-level role: a tiered, resilient architecture, defined reproducibly, with least-privilege network access, secrets handled correctly, monitoring in place, and a clean teardown. It is a portfolio piece as much as an exercise.

---

## 38.6 Portfolio and Interview Readiness

**A portfolio that speaks for you.** A public repository containing the capstone, with a clear README, an architecture diagram, and the reasoning behind the decisions, demonstrates more than a certificate does. It shows you can build the thing, not only answer questions about it. Add a short write-up of one problem you hit and how you diagnosed it, because troubleshooting is the skill interviews probe hardest and portfolios usually omit.

**What entry-level interviews actually test**

- **Fundamentals over trivia.** Explaining why a database goes in a private subnet matters more than reciting an instance type's specifications.
- **Troubleshooting method.** "An instance cannot reach the internet, walk me through your diagnosis" is a standard question. The method in section 38.3 is the answer: establish what changed, localize the layer, test one hypothesis. Say the steps aloud.
- **Trade-offs.** "When would you not use serverless" tests whether you understand the tool or only like it. Every good answer names a cost.
- **Real experience.** A project you actually built, that you can talk about concretely including what went wrong, beats a list of services.

**Common questions and the shape of a good answer**

- *Difference between a security group and a network ACL.* Stateful and instance-level versus stateless and subnet-level, and when each is the right control. Section 9.4.
- *How do you secure secrets.* Secrets Manager or Parameter Store, retrieved through a role, never in code or environment files. Section 17.7.
- *Multi-AZ versus read replica.* Availability versus read scaling, not interchangeable. Section 20.2.
- *How would you reduce this bill.* Right-size, commit for the baseline, Spot for interruptible work, and find the NAT and idle-resource waste. Section 30.2.
- *How do you deploy without downtime.* Blue/green or rolling, and the point that database migrations are the hard part. Section 27.4.

**Keeping current.** AWS changes constantly, as this course's own deprecation notes show. Follow the AWS News Blog and the What's New feed, read the Well-Architected Framework once through, and build things, because reading about a service and using it are different kinds of knowledge. The certifications in this course, the Cloud Practitioner and the Solutions Architect Associate, are a strong foundation; the habit of building on top of them is what turns the foundation into a career.

---

## 38.7 End-of-Chapter Questions

**Q1.** A credential is accidentally committed to a Git repository and removed in the next commit. What must be done?

- A. Nothing, since the latest commit no longer contains it
- B. Rotate the credential immediately, because it remains in the repository history and must be assumed compromised
- C. Delete the repository
- D. Add it to `.gitignore`

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Git history preserves the secret regardless of later commits, so rotation is the only safe response.

**Q2.** During an incident, what should happen before diagnosing the root cause?

- A. Write the postmortem
- B. Mitigate to restore service, for example by rolling back or failing over
- C. Identify who caused it
- D. Disable all alarms

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Restoring service comes first; understanding the cause is done once users are served.

**Q3.** In the capstone, why does the web tier security group reference the ALB security group as its source rather than a CIDR range?

- A. It is required by Terraform
- B. So that only the load balancer can reach the instances, regardless of the instances' addresses, which change as the group scales
- C. To reduce cost
- D. Because CIDR ranges are not allowed in private subnets

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Referencing the security group keeps the tier isolated as instances are replaced, which a fixed CIDR range cannot.

**Q4.** In the capstone Auto Scaling group, why is `health_check_type` set to `ELB` rather than `EC2`?

- A. It is cheaper
- B. So the group replaces instances the load balancer considers unhealthy, not only those failing an EC2 status check
- C. EC2 health checks do not work in private subnets
- D. It is required for Multi-AZ

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* An instance can pass EC2 status checks while its application is broken; ELB health checks let the group act on the application's actual health.

**Q5.** An interviewer asks how you would diagnose an instance that cannot reach the internet. What demonstrates the strongest answer?

- A. Naming the most likely single cause immediately
- B. Describing a method: establish what changed, localize the layer, test one hypothesis at a time, and verify
- C. Recommending a larger instance type
- D. Suggesting the instance be recreated

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Interviews test the method rather than a lucky guess, because the method is what generalizes to problems nobody has seen before.
