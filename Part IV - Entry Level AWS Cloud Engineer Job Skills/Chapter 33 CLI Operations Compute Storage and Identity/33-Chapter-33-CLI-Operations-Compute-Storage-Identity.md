# Chapter 33: CLI Operations: Compute, Storage, and Identity

---

Commands are shown for bash first, with PowerShell given where it differs meaningfully. Placeholders follow section 31.4.

**A note on `aws` versus `aws.exe`.** Both work in PowerShell with CLI v2. `aws.exe` is only needed if a PowerShell alias or function named `aws` shadows the executable, which is uncommon. The source notes for this course disagreed with themselves on this point; use `aws` unless it fails, then use `aws.exe`.

**Two syntax differences that matter in PowerShell**

- Single quotes inside `--query` must become double quotes.
- Line continuation is a backtick, not a backslash.

Before starting, confirm your identity and Region:

```bash
aws sts get-caller-identity
aws configure get region
```

---

## 33.1 Key Pairs

### 33.1.1 Create

```bash
aws ec2 create-key-pair \
  --key-name <KEY_NAME> \
  --key-type rsa \
  --key-format pem \
  --query 'KeyMaterial' --output text > <KEY_NAME>.pem
```

`--key-type` accepts `rsa`, the default, or `ed25519`, which is Linux only. `--key-format` accepts `pem`, the default, or `ppk` for PuTTY. Both are optional if the defaults suit.

![Creating a key pair with the AWS CLI](https://github.com/user-attachments/assets/7f41e464-1339-4509-a4ba-5c1a5701ffb7)

![Key pair creation output](https://github.com/user-attachments/assets/e8c2be0b-003b-460e-ae1d-fd2b05d9d6cc)

**PowerShell**

```powershell
aws ec2 create-key-pair --key-name <KEY_NAME> --key-type rsa --key-format pem --query "KeyMaterial" --output text > <KEY_NAME>.pem
```

![Creating a key pair in PowerShell](https://github.com/user-attachments/assets/f05a3b55-bbc9-4a5d-89b7-e7bcdd0cbfd2)

![Key pair file created in PowerShell](https://github.com/user-attachments/assets/e4ceb57b-1d47-4eb0-b58b-5da26a7b4b9d)

The private key is written to your terminal's output and captured by the redirect. It is not stored by AWS and cannot be retrieved again.

### 33.1.2 Secure the Private Key

**Linux and macOS**

```bash
chmod 400 <KEY_NAME>.pem
```

![Securing the private key file with chmod](https://github.com/user-attachments/assets/89593331-943f-4757-ad0b-d1330d6a5d83)

**PowerShell**

```powershell
icacls <KEY_NAME>.pem /inheritance:r /grant:r "$($env:USERNAME):(R)"
```

SSH refuses to use a key file that other users can read, so this step is not optional.

### 33.1.3 List

```bash
aws ec2 describe-key-pairs --query "KeyPairs[*].KeyName" --output text
aws ec2 describe-key-pairs --output table
```

![Listing key pairs](https://github.com/user-attachments/assets/9c6b8f73-3d19-4d29-9859-197b227cb219)

### 33.1.4 Delete

```bash
aws ec2 delete-key-pair --key-name <KEY_NAME>
rm -f <KEY_NAME>.pem
```

![Deleting a key pair](https://github.com/user-attachments/assets/a8311164-77ab-4ead-8222-b1ba108492cb)

Deleting the key pair in AWS does not affect instances already launched with it. They keep the public key in `authorized_keys`, so the local private key still works until it is removed from the instance.

---

## 33.2 Security Groups

### 33.2.1 Find the VPC

```bash
VPC_ID=$(aws ec2 describe-vpcs --query "Vpcs[0].VpcId" --output text)
echo "$VPC_ID"
```

![Retrieving the VPC ID](https://github.com/user-attachments/assets/9ea382fc-22c2-41ee-a39a-97e7bbe932bd)

**PowerShell**

```powershell
$VPC_ID = (aws ec2 describe-vpcs --query "Vpcs[0].VpcId" --output text)
$VPC_ID
```

![Retrieving the VPC ID in PowerShell](https://github.com/user-attachments/assets/33621b5e-504f-4f68-a8fb-52df94d6f198)

`Vpcs[0]` takes whichever VPC the API returns first, which is not necessarily the default one. In an account with several VPCs, select deliberately:

```bash
VPC_ID=$(aws ec2 describe-vpcs \
  --filters "Name=isDefault,Values=true" \
  --query "Vpcs[0].VpcId" --output text)
```

### 33.2.2 Create

```bash
SG_JSON=$(aws ec2 create-security-group \
  --group-name <SG_NAME> \
  --description "<DESCRIPTION>" \
  --vpc-id "$VPC_ID")

SG_ID=$(echo "$SG_JSON" | jq -r '.GroupId')
echo "$SG_ID"
```

![Creating a security group](https://github.com/user-attachments/assets/9632a38c-b0d7-4731-a9ba-66993acfb569)

**PowerShell**

```powershell
$sgCreate = aws ec2 create-security-group --group-name <SG_NAME> --description "<DESCRIPTION>" --vpc-id $VPC_ID --output json
$SG_ID = ($sgCreate | ConvertFrom-Json).GroupId
$SG_ID
```

![Creating a security group in PowerShell](https://github.com/user-attachments/assets/7d03ed0e-48a6-4948-8b29-17f646369872)

Name and description cannot be changed after creation, so choose them per the convention in section 31.4.

### 33.2.3 Authorize Ingress

**SSH, restricted to your own address**

```bash
MY_IP=$(curl -s https://checkip.amazonaws.com)
aws ec2 authorize-security-group-ingress \
  --group-id "$SG_ID" --protocol tcp --port 22 --cidr "${MY_IP}/32"
```

![Authorizing SSH ingress](https://github.com/user-attachments/assets/e755ebc2-afd6-4088-8aa7-051193d1427d)

![Authorizing SSH ingress in PowerShell](https://github.com/user-attachments/assets/c1cb4669-5337-4eee-ba2c-74cedf8e4655)

The source notes for this course used `0.0.0.0/0` for SSH throughout. That exposes port 22 to the entire internet and should not be copied. Restrict to a single address, or better, use Session Manager as covered in section 35.2 and open no inbound port at all.

**HTTP, which is legitimately public**

```bash
aws ec2 authorize-security-group-ingress \
  --group-id "$SG_ID" --protocol tcp --port 80 --cidr 0.0.0.0/0
```

![Authorizing HTTP ingress](https://github.com/user-attachments/assets/ef6242b4-4612-4a62-825f-b584f49b1b51)

**A database port, from one address only**

```bash
aws ec2 authorize-security-group-ingress \
  --group-id "$SG_ID" --protocol tcp --port 3306 --cidr <PUBLIC_IP>/32
```

**From another security group**, which is the pattern production should use, since it survives instances being replaced:

```bash
aws ec2 authorize-security-group-ingress \
  --group-id "$DB_SG_ID" --protocol tcp --port 3306 \
  --source-group "$APP_SG_ID"
```

**SMTP over IPv4**

```bash
aws ec2 authorize-security-group-ingress \
  --group-id "$SG_ID" --protocol tcp --port 25 --cidr 0.0.0.0/0
```

![Authorizing SMTP ingress](https://github.com/user-attachments/assets/cd1684e4-9717-4095-8b4e-7c1fb3144e06)

**SMTP over IPv6.** `--cidr` accepts IPv4 only, and there is no `--ipv6-cidr-block` option on this command. Use `--ip-permissions`:

```bash
aws ec2 authorize-security-group-ingress \
  --group-id "$SG_ID" \
  --ip-permissions '[{"IpProtocol":"tcp","FromPort":25,"ToPort":25,"Ipv6Ranges":[{"CidrIpv6":"::/0"}]}]'
```

![Authorizing SMTP over IPv6](https://github.com/user-attachments/assets/95b9dc1f-5b67-4b62-b41c-a67f78db98d4)

In PowerShell, quoting inline JSON is awkward, so write it to a file first:

```powershell
'[{"IpProtocol":"tcp","FromPort":25,"ToPort":25,"Ipv6Ranges":[{"CidrIpv6":"::/0"}]}]' |
  Out-File -FilePath ip-perms.json -Encoding ascii

aws ec2 authorize-security-group-ingress --group-id $SG_ID --ip-permissions file://ip-perms.json
```

**All traffic, which you should not do**

```bash
aws ec2 authorize-security-group-ingress \
  --group-id "$SG_ID" --protocol -1 --cidr 0.0.0.0/0
```

With `--protocol -1` do not supply `--port`; the CLI returns an error if you do.

![Authorizing all traffic](https://github.com/user-attachments/assets/63a723cc-7377-4aa2-acda-940ea1ff79ca)

![Authorizing all traffic in PowerShell](https://github.com/user-attachments/assets/94442d0a-5357-49ec-83e4-d35b04440a9b)

### 33.2.4 Inspect and Revoke

```bash
aws ec2 describe-security-groups --group-ids "$SG_ID"

aws ec2 describe-security-groups --group-ids "$SG_ID" \
  --query 'SecurityGroups[0].IpPermissions[].{Proto:IpProtocol,From:FromPort,To:ToPort,CIDR:IpRanges[].CidrIp}' \
  --output table
```

![Describing a security group](https://github.com/user-attachments/assets/706a4d8f-3418-40da-b981-c0d88ac84e71)

Revoking mirrors authorizing, with the same arguments:

```bash
aws ec2 revoke-security-group-ingress \
  --group-id "$SG_ID" --protocol tcp --port 22 --cidr "${MY_IP}/32"
```

![Revoking SSH ingress](https://github.com/user-attachments/assets/dd28319b-4b3d-4415-acf1-d9e2808c39a4)

Egress uses `authorize-security-group-egress` and `revoke-security-group-egress`. New groups have an allow-all egress rule; revoke it before adding narrower ones, or the broad rule keeps permitting everything.

### 33.2.5 Delete

```bash
aws ec2 delete-security-group --group-id "$SG_ID"
```

A group cannot be deleted while attached to any network interface, or while another security group references it as a source.

---

## 33.3 EC2 Instances

### 33.3.1 Gather the Inputs

```bash
AMI_ID=$(aws ssm get-parameters \
  --names /aws/service/ami-amazon-linux-latest/al2023-ami-kernel-default-x86_64 \
  --query 'Parameters[0].Value' --output text)

SUBNET_ID=$(aws ec2 describe-subnets \
  --filters "Name=vpc-id,Values=$VPC_ID" \
  --query 'Subnets[0].SubnetId' --output text)

echo "$AMI_ID $SUBNET_ID $SG_ID"
```

Resolving the AMI from Systems Manager Parameter Store is better than hardcoding an ID, because AMI IDs differ per Region and change with every release.

### 33.3.2 Launch

```bash
aws ec2 run-instances \
  --image-id "$AMI_ID" \
  --instance-type t3.micro \
  --key-name <KEY_NAME> \
  --security-group-ids "$SG_ID" \
  --subnet-id "$SUBNET_ID" \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=<TAG_VALUE>},{Key=Environment,Value=dev}]'
```

Capture the instance ID for later commands:

```bash
INSTANCE_ID=$(aws ec2 run-instances \
  --image-id "$AMI_ID" --instance-type t3.micro \
  --key-name <KEY_NAME> --security-group-ids "$SG_ID" --subnet-id "$SUBNET_ID" \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=<TAG_VALUE>}]' \
  --query 'Instances[0].InstanceId' --output text)
```

### 33.3.3 Wait, Query, and Connect

```bash
aws ec2 wait instance-running --instance-ids "$INSTANCE_ID"
aws ec2 wait instance-status-ok --instance-ids "$INSTANCE_ID"

PUBLIC_DNS=$(aws ec2 describe-instances --instance-ids "$INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].PublicDnsName' --output text)

ssh -i <KEY_NAME>.pem ec2-user@"$PUBLIC_DNS"
```

Waiters block until the condition is met, which is what makes scripts reliable. `instance-running` returns as soon as the hypervisor reports it running; `instance-status-ok` waits until it is actually reachable.

### 33.3.4 List and Filter

```bash
aws ec2 describe-instances \
  --query 'Reservations[].Instances[].[InstanceId,State.Name,Tags[?Key==`Name`].Value|[0]]' \
  --output table

aws ec2 describe-instances \
  --filters "Name=tag:Environment,Values=dev" "Name=instance-state-name,Values=running" \
  --query 'Reservations[].Instances[].InstanceId' --output text
```

In PowerShell, replace the outer single quotes with double quotes and escape the inner backticks.

### 33.3.5 Lifecycle

```bash
aws ec2 stop-instances --instance-ids "$INSTANCE_ID"
aws ec2 start-instances --instance-ids "$INSTANCE_ID"
aws ec2 reboot-instances --instance-ids "$INSTANCE_ID"
aws ec2 terminate-instances --instance-ids "$INSTANCE_ID"

aws ec2 wait instance-terminated --instance-ids "$INSTANCE_ID"
```

### 33.3.6 Region and Type Queries

```bash
aws ec2 describe-regions --output table

aws ec2 describe-availability-zones \
  --query 'AvailabilityZones[].{Name:ZoneName,ID:ZoneId,State:State}' --output table

aws ec2 describe-instance-type-offerings \
  --location-type availability-zone \
  --filters Name=instance-type,Values=t3.micro \
  --query 'InstanceTypeOfferings[].Location' --output table
```

The last one answers "can I launch this type in this zone", which is worth checking before a launch fails.

---

## 33.4 EBS Volumes and AMIs

### 33.4.1 Volumes

```bash
AZ=$(aws ec2 describe-instances --instance-ids "$INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].Placement.AvailabilityZone' --output text)

VOLUME_ID=$(aws ec2 create-volume \
  --availability-zone "$AZ" --size 10 --volume-type gp3 \
  --tag-specifications 'ResourceType=volume,Tags=[{Key=Name,Value=data-vol}]' \
  --query 'VolumeId' --output text)

aws ec2 wait volume-available --volume-ids "$VOLUME_ID"

aws ec2 attach-volume --volume-id "$VOLUME_ID" \
  --instance-id "$INSTANCE_ID" --device /dev/sdf
```

A volume can only attach to an instance in the same Availability Zone, which is why the zone is read from the instance rather than assumed.

```bash
aws ec2 detach-volume --volume-id "$VOLUME_ID"
aws ec2 wait volume-available --volume-ids "$VOLUME_ID"
aws ec2 delete-volume --volume-id "$VOLUME_ID"
```

**Finding orphans**, which is one of the most useful cost commands available:

```bash
aws ec2 describe-volumes --filters "Name=status,Values=available" \
  --query 'Volumes[].{ID:VolumeId,Size:Size,Created:CreateTime}' --output table
```

### 33.4.2 Snapshots

```bash
SNAPSHOT_ID=$(aws ec2 create-snapshot \
  --volume-id "$VOLUME_ID" --description "Pre-change backup" \
  --query 'SnapshotId' --output text)

aws ec2 wait snapshot-completed --snapshot-ids "$SNAPSHOT_ID"

aws ec2 describe-snapshots --owner-ids self --output table

aws ec2 delete-snapshot --snapshot-id "$SNAPSHOT_ID"
```

### 33.4.3 AMIs

```bash
AMI=$(aws ec2 create-image \
  --instance-id "$INSTANCE_ID" --name "app-server-$(date +%Y%m%d)" \
  --description "Configured application server" \
  --query 'ImageId' --output text)

aws ec2 wait image-available --image-ids "$AMI"

aws ec2 describe-images --owners self --output table

aws ec2 deregister-image --image-id "$AMI"
```

**Deregistering an image does not delete its snapshots.** They keep billing indefinitely. Find and remove them:

```bash
aws ec2 describe-snapshots --owner-ids self \
  --query 'Snapshots[?starts_with(Description, `Created by CreateImage`)].{ID:SnapshotId,Desc:Description}' \
  --output table
```

---

## 33.5 Elastic IP Addresses

```bash
ALLOC_ID=$(aws ec2 allocate-address --domain vpc --query 'AllocationId' --output text)

aws ec2 associate-address --instance-id "$INSTANCE_ID" --allocation-id "$ALLOC_ID"

aws ec2 disassociate-address --association-id <ASSOCIATION_ID>
aws ec2 release-address --allocation-id "$ALLOC_ID"
```

**Finding unassociated addresses**, which bill while idle:

```bash
aws ec2 describe-addresses \
  --query 'Addresses[?AssociationId==null].{IP:PublicIp,Alloc:AllocationId}' --output table
```

---

## 33.6 Amazon S3

The CLI offers two command sets. `aws s3` provides high-level file-like operations; `aws s3api` exposes the full API. Use `s3` for moving files and `s3api` for configuration.

### 33.6.1 Buckets

```bash
aws s3 ls

# us-east-1 takes no location constraint
aws s3api create-bucket --bucket <BUCKET> --region us-east-1

# every other Region requires one
aws s3api create-bucket --bucket <BUCKET> --region <REGION> \
  --create-bucket-configuration LocationConstraint=<REGION>

aws s3api get-bucket-location --bucket <BUCKET>

aws s3 rb s3://<BUCKET>
aws s3 rb s3://<BUCKET> --force     # deletes all contents first
```

The `--force` variant is irreversible on an unversioned bucket. On a versioned bucket it removes current versions only, leaving noncurrent versions and delete markers behind, which still bill.

### 33.6.2 Objects

```bash
aws s3 cp <LOCAL_FILE> s3://<BUCKET>/<PREFIX>/
aws s3 cp s3://<BUCKET>/<PREFIX>/<OBJECT_KEY> <LOCAL_FILE>
aws s3 cp <LOCAL_DIR>/ s3://<BUCKET>/<PREFIX>/ --recursive

aws s3 ls s3://<BUCKET>/<PREFIX>/
aws s3 ls s3://<BUCKET> --recursive --human-readable --summarize

aws s3 rm s3://<BUCKET>/<PREFIX>/<OBJECT_KEY>
aws s3 rm s3://<BUCKET>/<PREFIX>/ --recursive

aws s3 presign s3://<BUCKET>/<OBJECT_KEY> --expires-in 3600
```

### 33.6.3 Sync

```bash
aws s3 sync <LOCAL_DIR>/ s3://<BUCKET>/<PREFIX>/
aws s3 sync s3://<BUCKET>/<PREFIX>/ <LOCAL_DIR>/

aws s3 sync <LOCAL_DIR>/ s3://<BUCKET>/<PREFIX>/ \
  --exclude "*" --include "*.log"

aws s3 sync <LOCAL_DIR>/ s3://<BUCKET>/<PREFIX>/ --dryrun

aws s3 sync <LOCAL_DIR>/ s3://<BUCKET>/<PREFIX>/ --storage-class STANDARD_IA
```

`--dryrun` before any recursive or sync operation is a habit worth forming. Sync compares size and modification time, so it does not re-upload unchanged files, and `--delete` makes the destination mirror the source, which deletes things.

### 33.6.4 Encryption and Versioning

```bash
aws s3api put-bucket-versioning --bucket <BUCKET> \
  --versioning-configuration Status=Enabled

aws s3api put-bucket-encryption --bucket <BUCKET> \
  --server-side-encryption-configuration '{
    "Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"}}]
  }'

aws s3api get-bucket-encryption --bucket <BUCKET>

aws s3 cp <LOCAL_FILE> s3://<BUCKET>/ --sse aws:kms --sse-kms-key-id <KMS_KEY_ID>

aws s3api list-object-versions --bucket <BUCKET> --prefix <PREFIX>
aws s3api delete-object --bucket <BUCKET> --key <OBJECT_KEY> --version-id <VERSION_ID>
```

New buckets already apply SSE-S3 by default, so `put-bucket-encryption` with AES256 is confirming rather than changing. Setting SSE-KMS is the meaningful change, for the audit trail described in section 18.4.

---

## 33.7 IAM

### 33.7.1 Users and Groups

```bash
aws iam list-users --output table
aws iam create-user --user-name <USER_NAME>

aws iam create-group --group-name <GROUP_NAME>
aws iam attach-group-policy --group-name <GROUP_NAME> \
  --policy-arn arn:aws:iam::aws:policy/ReadOnlyAccess
aws iam add-user-to-group --user-name <USER_NAME> --group-name <GROUP_NAME>

aws iam list-groups-for-user --user-name <USER_NAME>
aws iam list-attached-group-policies --group-name <GROUP_NAME>
```

Attach policies to groups, not directly to users, per section 8.2.

### 33.7.2 Policies

```bash
aws iam list-policies --scope AWS --query 'Policies[?contains(PolicyName, `S3`)].PolicyName' --output text

aws iam create-policy --policy-name <POLICY_NAME> --policy-document file://policy.json

aws iam create-policy-version --policy-arn <POLICY_ARN> \
  --policy-document file://policy-v2.json --set-as-default

aws iam list-policy-versions --policy-arn <POLICY_ARN>
aws iam delete-policy-version --policy-arn <POLICY_ARN> --version-id v1
```

A managed policy holds at most five versions, so pruning old ones is required before creating a sixth.

### 33.7.3 Roles

```bash
cat > trust-ec2.json <<'EOF'
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": { "Service": "ec2.amazonaws.com" },
    "Action": "sts:AssumeRole"
  }]
}
EOF

aws iam create-role --role-name <ROLE_NAME> \
  --assume-role-policy-document file://trust-ec2.json

aws iam attach-role-policy --role-name <ROLE_NAME> \
  --policy-arn arn:aws:iam::aws:policy/AmazonS3ReadOnlyAccess

aws iam put-role-policy --role-name <ROLE_NAME> \
  --policy-name inline-logging --policy-document file://logging.json

aws iam list-attached-role-policies --role-name <ROLE_NAME>
```

To attach a role to an EC2 instance, an instance profile is also required:

```bash
aws iam create-instance-profile --instance-profile-name <ROLE_NAME>
aws iam add-role-to-instance-profile \
  --instance-profile-name <ROLE_NAME> --role-name <ROLE_NAME>

aws ec2 associate-iam-instance-profile \
  --instance-id "$INSTANCE_ID" --iam-instance-profile Name=<ROLE_NAME>
```

The console creates the instance profile silently, which is why this step surprises people working from the CLI.

### 33.7.4 Access Keys

```bash
aws iam create-access-key --user-name <USER_NAME>
aws iam list-access-keys --user-name <USER_NAME>
aws iam update-access-key --user-name <USER_NAME> --access-key-id <KEY_ID> --status Inactive
aws iam delete-access-key --user-name <USER_NAME> --access-key-id <KEY_ID>
```

Rotation is: create the new key, deploy it, deactivate the old one, confirm nothing broke, then delete. Deactivating before deleting makes the rollback a single command.

### 33.7.5 Permission Boundaries

```bash
aws iam create-policy --policy-name <BOUNDARY_NAME> --policy-document file://boundary.json

aws iam create-role --role-name <ROLE_NAME> \
  --assume-role-policy-document file://trust-ec2.json \
  --permissions-boundary <POLICY_ARN>

aws iam get-role --role-name <ROLE_NAME> --query 'Role.PermissionsBoundary'
```

### 33.7.6 Analysis and Auditing

```bash
aws iam simulate-principal-policy \
  --policy-source-arn <PRINCIPAL_ARN> \
  --action-names s3:GetObject s3:PutObject \
  --resource-arns arn:aws:s3:::<BUCKET>/*

aws accessanalyzer validate-policy \
  --policy-document file://policy.json --policy-type IDENTITY_POLICY

aws iam generate-service-last-accessed-details --arn <ROLE_ARN>

aws iam list-roles --query 'Roles[].{Name:RoleName,Created:CreateDate}' --output table
aws iam get-account-summary

aws iam generate-credential-report
aws iam get-credential-report --query 'Content' --output text | base64 --decode
```

`simulate-principal-policy` answers "would this identity be allowed to do this" without attempting it, which is the fastest way to debug an `AccessDenied` without granting anything. The credential report lists every user, when their password and keys were last used, and whether MFA is enabled, which is the starting point for an access review.

---

## 33.8 Cleanup Sequences

Resources hold dependencies, so order matters. Deleting in the wrong order produces errors that look like permission problems.

### 33.8.1 Compute and Networking

```bash
# 1. Terminate instances and wait
aws ec2 terminate-instances --instance-ids "$INSTANCE_ID"
aws ec2 wait instance-terminated --instance-ids "$INSTANCE_ID"

# 2. Release Elastic IPs
aws ec2 release-address --allocation-id "$ALLOC_ID"

# 3. Detach and delete volumes not removed with the instance
aws ec2 delete-volume --volume-id "$VOLUME_ID"

# 4. Deregister AMIs, then delete their snapshots
aws ec2 deregister-image --image-id "$AMI"
aws ec2 delete-snapshot --snapshot-id "$SNAPSHOT_ID"

# 5. Delete security groups, referencing groups first
aws ec2 delete-security-group --group-id "$SG_ID"

# 6. Delete key pairs
aws ec2 delete-key-pair --key-name <KEY_NAME>
```

### 33.8.2 Deleting an IAM User Completely

A user cannot be deleted while anything is attached to it.

```bash
aws iam list-access-keys --user-name <USER_NAME>
aws iam delete-access-key --user-name <USER_NAME> --access-key-id <KEY_ID>

aws iam delete-login-profile --user-name <USER_NAME>

aws iam list-user-policies --user-name <USER_NAME>
aws iam delete-user-policy --user-name <USER_NAME> --policy-name <POLICY_NAME>

aws iam list-attached-user-policies --user-name <USER_NAME>
aws iam detach-user-policy --user-name <USER_NAME> --policy-arn <POLICY_ARN>

aws iam list-groups-for-user --user-name <USER_NAME>
aws iam remove-user-from-group --user-name <USER_NAME> --group-name <GROUP_NAME>

aws iam list-mfa-devices --user-name <USER_NAME>
aws iam deactivate-mfa-device --user-name <USER_NAME> --serial-number <SERIAL>

aws iam delete-user --user-name <USER_NAME>
```

### 33.8.3 Deleting a Role and a Customer-Managed Policy

```bash
aws iam remove-role-from-instance-profile \
  --instance-profile-name <ROLE_NAME> --role-name <ROLE_NAME>
aws iam delete-instance-profile --instance-profile-name <ROLE_NAME>

aws iam list-role-policies --role-name <ROLE_NAME>
aws iam delete-role-policy --role-name <ROLE_NAME> --policy-name <POLICY_NAME>

aws iam list-attached-role-policies --role-name <ROLE_NAME>
aws iam detach-role-policy --role-name <ROLE_NAME> --policy-arn <POLICY_ARN>

aws iam delete-role --role-name <ROLE_NAME>

# A policy must be detached from every entity, and non-default versions removed
aws iam list-entities-for-policy --policy-arn <POLICY_ARN>
aws iam list-policy-versions --policy-arn <POLICY_ARN>
aws iam delete-policy-version --policy-arn <POLICY_ARN> --version-id v1
aws iam delete-policy --policy-arn <POLICY_ARN>
```

---

## 33.9 End-of-Chapter Questions

**Q1.** After running `aws ec2 create-key-pair` and redirecting the output to a file, the engineer loses the file. How can the private key be recovered?

- A. Run `describe-key-pairs` with the `--include-material` flag
- B. Download it from the EC2 console
- C. It cannot be recovered; AWS stores only the public key, so a new key pair must be created
- D. Open a support case

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* The private key is returned once at creation and never stored by AWS.

**Q2.** A security group cannot be deleted, and the error mentions a dependency. What are the two most likely causes?

- A. The group has no rules, and the VPC is default
- B. It is attached to a network interface, or another security group references it as a rule source
- C. It was created in the wrong Region, and the account lacks permission
- D. Versioning is enabled on the VPC

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Both an attachment and an inbound reference from another group block deletion, which is why cleanup order matters.

**Q3.** An engineer deregisters several AMIs to reduce cost but the EBS bill does not fall. Why?

- A. Deregistering takes 30 days to take effect
- B. Deregistering an image does not delete the underlying snapshots, which continue to bill
- C. AMIs are billed separately from snapshots
- D. The images were shared with another account

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* The snapshots backing a deregistered image persist until deleted explicitly.

**Q4.** A CLI script attaches a role to an EC2 instance but the `associate-iam-instance-profile` command fails. What is most likely missing?

- A. The role's trust policy
- B. An instance profile, which the console creates automatically but the CLI does not
- C. A permissions boundary
- D. The instance must be stopped first

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* EC2 attaches an instance profile rather than a role directly, and from the CLI the profile must be created and the role added to it explicitly.

**Q5.** An engineer needs to check whether a role would be permitted to call `s3:PutObject` on a bucket, without granting anything or attempting the call. Which command answers this?

- A. `aws iam get-role`
- B. `aws iam simulate-principal-policy`
- C. `aws iam generate-credential-report`
- D. `aws sts get-caller-identity`

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Policy simulation evaluates the request against all applicable policies and reports the outcome without performing the action.
