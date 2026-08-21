# Chapter 35: CLI Operations: Automation, Containers, and CloudShell

---

Section 27.2 covered CloudFormation as a design tool. This chapter covers operating it, along with Systems Manager, the container registry and Kubernetes services, and the browser shell.

---

## 35.1 AWS CloudFormation

### 35.1.1 Validate and Deploy

```bash
aws cloudformation validate-template --template-body file://template.yaml

aws cloudformation deploy \
  --template-file template.yaml \
  --stack-name <STACK_NAME> \
  --parameter-overrides <PARAM_KEY>=<PARAM_VALUE> Environment=dev \
  --capabilities CAPABILITY_NAMED_IAM \
  --tags Environment=dev Owner=platform
```

`deploy` creates the stack if absent and updates it if present, which makes it the command to use in a pipeline. `--capabilities CAPABILITY_NAMED_IAM` is required whenever the template creates IAM resources with explicit names, and its absence is a common first failure.

`validate-template` checks syntax only. It does not check whether the resources can actually be created.

### 35.1.2 Change Sets

```bash
aws cloudformation create-change-set \
  --stack-name <STACK_NAME> --change-set-name <CHANGE_SET_NAME> \
  --template-body file://template.yaml \
  --parameters ParameterKey=<PARAM_KEY>,ParameterValue=<PARAM_VALUE> \
  --capabilities CAPABILITY_NAMED_IAM

aws cloudformation wait change-set-create-complete \
  --stack-name <STACK_NAME> --change-set-name <CHANGE_SET_NAME>

aws cloudformation describe-change-set \
  --stack-name <STACK_NAME> --change-set-name <CHANGE_SET_NAME> \
  --query 'Changes[].ResourceChange.{Action:Action,Type:ResourceType,Id:LogicalResourceId,Replace:Replacement}' \
  --output table

aws cloudformation execute-change-set \
  --stack-name <STACK_NAME> --change-set-name <CHANGE_SET_NAME>
```

The `Replace` column is the one that matters. `True` means the resource will be destroyed and recreated, which for a database or a stateful volume means data loss. Never execute a production change set without reading it.

### 35.1.3 Query Stacks

```bash
aws cloudformation describe-stacks --stack-name <STACK_NAME> \
  --query 'Stacks[0].{Status:StackStatus,Created:CreationTime,Drift:DriftInformation.StackDriftStatus}'

aws cloudformation list-stacks \
  --stack-status-filter CREATE_COMPLETE UPDATE_COMPLETE \
  --query 'StackSummaries[].{Name:StackName,Status:StackStatus}' --output table

aws cloudformation describe-stack-events --stack-name <STACK_NAME> \
  --query 'StackEvents[0:20].{Time:Timestamp,Status:ResourceStatus,Type:ResourceType,Reason:ResourceStatusReason}' \
  --output table

aws cloudformation describe-stack-resources --stack-name <STACK_NAME> --output table
```

When a stack fails, `describe-stack-events` is where the cause is. Read from the bottom of the failure upward: the first `CREATE_FAILED` carries the real reason, and everything after it is rollback noise.

### 35.1.4 Outputs and Exports

```bash
aws cloudformation describe-stacks --stack-name <STACK_NAME> \
  --query 'Stacks[0].Outputs[].{Key:OutputKey,Value:OutputValue}' --output table

VPC_ID=$(aws cloudformation describe-stacks --stack-name <STACK_NAME> \
  --query 'Stacks[0].Outputs[?OutputKey==`VpcId`].OutputValue' --output text)

aws cloudformation list-exports --query 'Exports[].{Name:Name,Value:Value}' --output table
aws cloudformation list-imports --export-name <EXPORT_NAME>
```

`list-imports` is what to run before changing an export. An export cannot be modified or deleted while another stack imports it, and this command names the stacks blocking you.

### 35.1.5 Waiters and Drift

```bash
aws cloudformation wait stack-create-complete --stack-name <STACK_NAME>
aws cloudformation wait stack-update-complete --stack-name <STACK_NAME>
aws cloudformation wait stack-delete-complete --stack-name <STACK_NAME>

DRIFT_ID=$(aws cloudformation detect-stack-drift --stack-name <STACK_NAME> \
  --query 'StackDriftDetectionId' --output text)

aws cloudformation describe-stack-drift-detection-status \
  --stack-drift-detection-id "$DRIFT_ID"

aws cloudformation describe-stack-resource-drifts --stack-name <STACK_NAME> \
  --stack-resource-drift-status-filters MODIFIED DELETED \
  --query 'StackResourceDrifts[].{Id:LogicalResourceId,Status:StackResourceDriftStatus}' \
  --output table
```

Drift detection is asynchronous: start it, then poll for the result.

### 35.1.6 Protection and Packaging

```bash
aws cloudformation set-stack-policy --stack-name <STACK_NAME> \
  --stack-policy-body file://stack-policy.json

aws cloudformation update-termination-protection \
  --stack-name <STACK_NAME> --enable-termination-protection

aws cloudformation package \
  --template-file template.yaml \
  --s3-bucket <BUCKET> \
  --output-template-file packaged.yaml
```

`package` uploads local artifacts, such as Lambda code and nested stack templates, to S3 and rewrites the template to reference them. It is required before deploying any template with local file references.

### 35.1.7 StackSets

```bash
aws cloudformation create-stack-set --stack-set-name <STACK_SET_NAME> \
  --template-body file://baseline.yaml \
  --permission-model SERVICE_MANAGED \
  --auto-deployment Enabled=true,RetainStacksOnAccountRemoval=false \
  --capabilities CAPABILITY_NAMED_IAM

aws cloudformation create-stack-instances --stack-set-name <STACK_SET_NAME> \
  --deployment-targets OrganizationalUnitIds=<OU_ID> \
  --regions us-east-1 eu-west-1 \
  --operation-preferences FailureTolerancePercentage=10,MaxConcurrentPercentage=25

aws cloudformation list-stack-instances --stack-set-name <STACK_SET_NAME> --output table
```

`SERVICE_MANAGED` with `auto-deployment` enrolls new accounts joining the organizational unit automatically, which is the behavior described in section 27.2.

### 35.1.8 Delete

```bash
aws cloudformation update-termination-protection \
  --stack-name <STACK_NAME> --no-enable-termination-protection

aws cloudformation delete-stack --stack-name <STACK_NAME>
aws cloudformation wait stack-delete-complete --stack-name <STACK_NAME>

# Retain a resource that would otherwise block deletion
aws cloudformation delete-stack --stack-name <STACK_NAME> \
  --retain-resources <LOGICAL_ID>
```

A stack holding a non-empty S3 bucket fails to delete. Empty the bucket first, or retain it.

---

## 35.2 AWS Systems Manager

### 35.2.1 Session Manager

```bash
aws ssm start-session --target <INSTANCE_ID>

aws ssm start-session --target <INSTANCE_ID> \
  --document-name AWS-StartPortForwardingSession \
  --parameters '{"portNumber":["3306"],"localPortNumber":["3306"]}'

aws ssm start-session --target <INSTANCE_ID> \
  --document-name AWS-StartPortForwardingSessionToRemoteHost \
  --parameters '{"host":["<RDS_ENDPOINT>"],"portNumber":["3306"],"localPortNumber":["3306"]}'
```

This is the replacement for SSH and bastion hosts. It requires no inbound security group rule, no key pair, and no public IP. The prerequisites are the SSM Agent, which is preinstalled on current Amazon Linux, Ubuntu, and Windows AMIs, an instance profile with `AmazonSSMManagedInstanceCore`, and outbound connectivity to the SSM endpoints, either through a NAT gateway or interface endpoints.

The third command port-forwards through the instance to a private RDS endpoint, which is how you reach a database in a private subnet from a laptop without a bastion.

**Confirm an instance is managed**

```bash
aws ssm describe-instance-information \
  --query 'InstanceInformationList[].{ID:InstanceId,Ping:PingStatus,Platform:PlatformName,Agent:AgentVersion}' \
  --output table
```

An instance absent from this list is not manageable, and the cause is almost always the missing instance profile or no route to the SSM endpoints.

### 35.2.2 Run Command

```bash
CMD_ID=$(aws ssm send-command \
  --document-name "AWS-RunShellScript" \
  --targets "Key=tag:Environment,Values=dev" \
  --parameters 'commands=["df -h","uptime"]' \
  --comment "Disk and uptime check" \
  --query 'Command.CommandId' --output text)

aws ssm list-command-invocations --command-id "$CMD_ID" --details \
  --query 'CommandInvocations[].{Instance:InstanceId,Status:Status,Output:CommandPlugins[0].Output}' \
  --output table

aws ssm get-command-invocation --command-id "$CMD_ID" --instance-id <INSTANCE_ID>
```

Targeting by tag rather than instance ID is what makes this scale. One command reaches every instance carrying the tag, including ones launched after the script was written.

### 35.2.3 Parameter Store

```bash
aws ssm put-parameter --name <PARAMETER_NAME> \
  --value "<PARAMETER_VALUE>" --type String

aws ssm put-parameter --name /app/prod/db-password \
  --value "<PARAMETER_VALUE>" --type SecureString --key-id alias/aws/ssm

aws ssm get-parameter --name <PARAMETER_NAME> --query 'Parameter.Value' --output text
aws ssm get-parameter --name /app/prod/db-password --with-decryption \
  --query 'Parameter.Value' --output text

aws ssm get-parameters-by-path --path /app/prod --recursive --with-decryption \
  --query 'Parameters[].{Name:Name,Value:Value}' --output table

aws ssm put-parameter --name <PARAMETER_NAME> --value "new" --type String --overwrite
aws ssm delete-parameter --name <PARAMETER_NAME>
```

Hierarchical names such as `/app/prod/db-password` let one IAM policy grant access to an entire environment by path, and `get-parameters-by-path` retrieves a whole configuration set in one call.

### 35.2.4 Patch Manager and Automation

```bash
aws ssm create-patch-baseline --name <BASELINE_NAME> \
  --operating-system AMAZON_LINUX_2023 \
  --approval-rules "PatchRules=[{PatchFilterGroup={PatchFilters=[{Key=CLASSIFICATION,Values=[Security]}]},ApproveAfterDays=7,ComplianceLevel=CRITICAL}]"

aws ssm send-command --document-name "AWS-RunPatchBaseline" \
  --targets "Key=tag:Environment,Values=dev" \
  --parameters 'Operation=Install'

aws ssm describe-instance-patch-states --instance-ids <INSTANCE_ID>

aws ssm start-automation-execution \
  --document-name "AWS-RestartEC2Instance" \
  --parameters "InstanceId=<INSTANCE_ID>"

aws ssm describe-automation-executions \
  --query 'AutomationExecutionMetadataList[0:5].{Doc:DocumentName,Status:AutomationExecutionStatus,Start:ExecutionStartTime}' \
  --output table
```

### 35.2.5 Inventory and Compliance

```bash
aws ssm list-inventory-entries --instance-id <INSTANCE_ID> \
  --type-name "AWS:Application" --max-results 20

aws ssm list-compliance-summaries --output table

aws ssm create-maintenance-window --name <WINDOW_NAME> \
  --schedule "cron(0 2 ? * SUN *)" --duration 4 --cutoff 1 \
  --allow-unassociated-targets
```

---

## 35.3 Amazon ECR

```bash
aws ecr create-repository --repository-name <REPOSITORY_NAME> \
  --image-scanning-configuration scanOnPush=true \
  --image-tag-mutability IMMUTABLE \
  --encryption-configuration encryptionType=AES256

aws ecr describe-repositories --output table
```

`IMMUTABLE` tags prevent a tag being repointed to different content, which is what makes a deployed image reproducible. Use it in production.

**Authenticate and push**

```bash
aws ecr get-login-password --region "$REGION" | \
  docker login --username AWS --password-stdin "$ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com"

docker build -t <REPOSITORY_NAME>:<IMAGE_TAG> .
docker tag <REPOSITORY_NAME>:<IMAGE_TAG> \
  "$ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com/<REPOSITORY_NAME>:<IMAGE_TAG>"
docker push "$ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com/<REPOSITORY_NAME>:<IMAGE_TAG>"
```

The login token expires after 12 hours, so scripts must re-authenticate rather than assume a cached login.

**Scanning, lifecycle, and cleanup**

```bash
aws ecr describe-image-scan-findings \
  --repository-name <REPOSITORY_NAME> --image-id imageTag=<IMAGE_TAG> \
  --query 'imageScanFindings.findingSeverityCounts'

aws ecr put-lifecycle-policy --repository-name <REPOSITORY_NAME> \
  --lifecycle-policy-text '{
    "rules":[{
      "rulePriority":1,
      "description":"Keep the last 10 images",
      "selection":{"tagStatus":"any","countType":"imageCountMoreThan","countNumber":10},
      "action":{"type":"expire"}
    }]
  }'

aws ecr list-images --repository-name <REPOSITORY_NAME> --output table
aws ecr batch-delete-image --repository-name <REPOSITORY_NAME> \
  --image-ids imageTag=<IMAGE_TAG>
aws ecr delete-repository --repository-name <REPOSITORY_NAME> --force
```

Without a lifecycle policy, every build accumulates forever. This is one of the quietest recurring costs in a container estate.

**Multi-architecture images**

```bash
docker buildx build --platform linux/amd64,linux/arm64 \
  -t "$ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com/<REPOSITORY_NAME>:<IMAGE_TAG>" --push .
```

This matters when moving workloads to Graviton, since an amd64-only image will not run on arm64 nodes.

---

## 35.4 Amazon EKS

```bash
aws eks create-cluster --name <CLUSTER_NAME> \
  --role-arn "arn:aws:iam::$ACCOUNT_ID:role/<ROLE_NAME>" \
  --resources-vpc-config subnetIds=<SUBNET_ID_A>,<SUBNET_ID_B>,securityGroupIds=<SG_ID> \
  --kubernetes-version 1.31

aws eks wait cluster-active --name <CLUSTER_NAME>
aws eks describe-cluster --name <CLUSTER_NAME> \
  --query 'cluster.{Status:status,Version:version,Endpoint:endpoint}'
aws eks list-clusters
```

**Configure kubectl**

```bash
aws eks update-kubeconfig --name <CLUSTER_NAME> --region "$REGION"
kubectl get nodes
kubectl get pods --all-namespaces
```

`update-kubeconfig` writes the cluster context and configures authentication through the CLI, so `kubectl` uses your AWS credentials. If `kubectl` reports an authorization error afterward, the IAM identity is not mapped to a Kubernetes group, which is an access entry or `aws-auth` configuration issue rather than an AWS permissions one.

**Node groups and Fargate**

```bash
aws eks create-nodegroup --cluster-name <CLUSTER_NAME> \
  --nodegroup-name <NODEGROUP_NAME> \
  --node-role "arn:aws:iam::$ACCOUNT_ID:role/<NODE_ROLE>" \
  --subnets <SUBNET_ID_A> <SUBNET_ID_B> \
  --instance-types t3.medium \
  --scaling-config minSize=2,maxSize=6,desiredSize=2 \
  --capacity-type ON_DEMAND

aws eks wait nodegroup-active --cluster-name <CLUSTER_NAME> --nodegroup-name <NODEGROUP_NAME>

aws eks update-nodegroup-config --cluster-name <CLUSTER_NAME> \
  --nodegroup-name <NODEGROUP_NAME> --scaling-config minSize=2,maxSize=10,desiredSize=4

aws eks create-fargate-profile --cluster-name <CLUSTER_NAME> \
  --fargate-profile-name <PROFILE_NAME> \
  --pod-execution-role-arn "arn:aws:iam::$ACCOUNT_ID:role/<POD_ROLE>" \
  --subnets <PRIVATE_SUBNET_A> <PRIVATE_SUBNET_B> \
  --selectors namespace=<NAMESPACE>
```

Fargate profiles require **private** subnets. Supplying public ones fails.

**Add-ons, upgrades, and IRSA**

```bash
aws eks create-addon --cluster-name <CLUSTER_NAME> --addon-name vpc-cni
aws eks list-addons --cluster-name <CLUSTER_NAME>
aws eks update-addon --cluster-name <CLUSTER_NAME> --addon-name vpc-cni --addon-version <VERSION>

aws eks update-cluster-version --name <CLUSTER_NAME> --kubernetes-version 1.32
aws eks update-nodegroup-version --cluster-name <CLUSTER_NAME> --nodegroup-name <NODEGROUP_NAME>

# IAM roles for service accounts
aws eks describe-cluster --name <CLUSTER_NAME> \
  --query 'cluster.identity.oidc.issuer' --output text

aws iam create-open-id-connect-provider \
  --url <OIDC_URL> --client-id-list sts.amazonaws.com \
  --thumbprint-list <THUMBPRINT>
```

**Upgrade the control plane before the node groups.** Nodes may run one or two minor versions behind the control plane, never ahead.

**Delete**

```bash
aws eks delete-nodegroup --cluster-name <CLUSTER_NAME> --nodegroup-name <NODEGROUP_NAME>
aws eks wait nodegroup-deleted --cluster-name <CLUSTER_NAME> --nodegroup-name <NODEGROUP_NAME>
aws eks delete-fargate-profile --cluster-name <CLUSTER_NAME> --fargate-profile-name <PROFILE_NAME>
aws eks delete-cluster --name <CLUSTER_NAME>
```

Node groups and Fargate profiles must go before the cluster. Also check for load balancers created by Kubernetes Service objects, which are not removed by deleting the cluster and will keep billing.

---

## 35.5 AWS CloudShell

A browser-based shell, launched from the console toolbar, preauthenticated as your signed-in identity.

**What it provides**

- The AWS CLI v2, Python, Node.js, Git, `jq`, `zip`, and the SSM Session Manager plugin, preinstalled.
- 1 GB of persistent storage per Region in your home directory.
- No cost for the service; you pay only for AWS resources you create.

**Why it is useful**

- No credentials on your laptop, which satisfies policies that forbid local access keys.
- Nothing to install, so it works from any machine including one you do not control.
- Always current, since AWS maintains the tooling.

**What to know**

- **Only the home directory persists.** Anything outside `~` is lost when the session ends. Sessions time out after about 20 to 30 minutes of inactivity, and the environment is rebuilt on next use.
- **Storage is per Region.** Files created in `us-east-1` are not visible from `eu-west-1`.
- **It inherits your console identity**, so it has exactly the permissions of the role or user you signed in as.
- **Not suitable for long-running processes**, because the session ends when you close the tab or go idle.

**Making an environment reproducible**

```bash
mkdir -p ~/bin ~/scripts

cat >> ~/.bashrc <<'EOF'
export PATH="$HOME/bin:$PATH"
export AWS_PAGER=""
alias ll='ls -alF'
alias whoami-aws='aws sts get-caller-identity --output table'
EOF

pip3 install --user boto3
```

Installing to `--user` places packages under the home directory so they survive. Anything installed with `sudo` to a system path does not.

**Uploading and downloading**

Use **Actions**, then **Upload file** or **Download file**, in the CloudShell toolbar. For anything large, use S3 as the intermediary:

```bash
aws s3 cp s3://<BUCKET>/<OBJECT_KEY> .
aws s3 cp ./output.json s3://<BUCKET>/results/
```

**A useful pattern: running one command across Regions**

```bash
for r in us-east-1 eu-west-1 ap-southeast-2; do
  echo "== $r"
  aws ec2 describe-instances --region "$r" \
    --filters "Name=instance-state-name,Values=running" \
    --query 'Reservations[].Instances[].[InstanceId,InstanceType]' --output text
done
```

This is the fastest way to answer "what is running in this account" across every Region, which is where forgotten resources hide.

---

## 35.6 Docker Fundamentals for Cloud Engineers

Enough to work with ECR, ECS, and EKS.

**Images and containers.** An image is a read-only template built in layers. A container is a running instance of one. Layers are cached and shared, which is why ordering instructions well makes builds fast.

**A Dockerfile that behaves in production**

```dockerfile
FROM public.ecr.aws/docker/library/python:3.13-slim

WORKDIR /app

# Dependencies first, so this layer caches when only app code changes
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# Do not run as root
RUN useradd -m appuser
USER appuser

EXPOSE 8080
CMD ["python", "app.py"]
```

Three practices in that file matter: copying dependency manifests before application code so the dependency layer caches, using a slim base image to reduce size and attack surface, and running as a non-root user.

Pulling base images from the **Amazon ECR Public Gallery** rather than Docker Hub avoids Docker Hub's anonymous pull rate limits, which is a common cause of intermittent build failures in CI.

**Commands worth knowing**

```bash
docker build -t <REPOSITORY_NAME>:<IMAGE_TAG> .
docker images
docker run -d -p 8080:8080 --name app <REPOSITORY_NAME>:<IMAGE_TAG>
docker ps
docker logs -f app
docker exec -it app /bin/sh
docker stop app && docker rm app
docker system prune -a          # reclaims disk, removes unused images
```

**Configuration and secrets.** Pass configuration through environment variables, and never bake secrets into an image. Layers persist even when a later instruction deletes a file, so a secret added and removed is still recoverable from the image. Use Secrets Manager or Parameter Store, retrieved at startup through a task role.

**The build-to-ECR workflow**

1. Build the image locally or in CodeBuild.
2. Authenticate to ECR with `get-login-password`.
3. Tag the image with the full registry URI.
4. Push.
5. Reference the image URI in an ECS task definition or Kubernetes manifest.

---

## 35.7 End-of-Chapter Questions

**Q1.** A CloudFormation change set shows `Replacement: True` against an RDS resource. What does executing it do?

- A. Update the instance in place with no interruption
- B. Destroy the existing database and create a new one, losing the data unless a snapshot or retention policy exists
- C. Fail with a validation error
- D. Queue the change for the next maintenance window

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Replacement creates a new resource and deletes the old one, which is why change sets must be read before execution.

**Q2.** An EC2 instance does not appear in `aws ssm describe-instance-information`. What are the two most likely causes?

- A. The instance is stopped, and the AMI is unsupported
- B. Missing instance profile with `AmazonSSMManagedInstanceCore`, or no network route to the SSM endpoints
- C. Session Manager is not enabled in the Region
- D. The security group has no inbound rule for port 22

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Session Manager connects outbound, so no inbound rule is needed; the agent needs both permissions and a path to the endpoints.

**Q3.** An ECR repository grows continuously and storage costs rise with every build. What should be configured?

- A. Image scanning on push
- B. Immutable tags
- C. A lifecycle policy that expires images beyond a retained count
- D. Cross-Region replication

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Without a lifecycle policy every pushed image is retained indefinitely.

**Q4.** Files an engineer created in `/tmp` during a CloudShell session are gone the next day, while files in the home directory remain. Why?

- A. CloudShell storage is per Region and the Region changed
- B. Only the home directory is persisted; everything else is rebuilt with the environment
- C. The session exceeded the storage quota
- D. The files were uploaded to S3 automatically

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* CloudShell persists 1 GB in the home directory per Region and discards the rest when the session ends.

**Q5.** An EKS cluster upgrade is planned. What is the correct order?

- A. Upgrade node groups first, then the control plane
- B. Upgrade the control plane first, then the node groups
- C. Upgrade both simultaneously
- D. Recreate the cluster

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Nodes may run behind the control plane version but never ahead of it.

**Q6.** A Dockerfile copies the whole application directory before installing dependencies. What is the consequence?

- A. The image will not build
- B. Every application code change invalidates the dependency layer, so dependencies are reinstalled on every build
- C. Secrets are exposed in the image
- D. The container cannot run as a non-root user

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Layer caching depends on instruction order; copying dependency manifests first keeps that layer valid when only application code changes.
