# Chapter 7: Getting Access to AWS

---
This is the first hands-on chapter. By the end of it you will have an account, a secured root user, a working sign-in path, a budget alert, and two resources created by hand in the console.

Everything from here on assumes you have completed sections 7.1 to 7.4. Do them in order, because the security steps are much harder to retrofit than to apply at the start.

---
## 7.1 Creating an AWS Account

1. Open https://aws.amazon.com in a browser.
2. Choose **Create an AWS Account**.
3. Enter a root user email address. Use an address you control long term, ideally a distribution list rather than one person's mailbox, because losing access to it is a serious problem.
4. Enter an account name. This is a label and can be changed later.
5. Set a root password. It must be at least 8 characters and combine uppercase, lowercase, numbers, and symbols, and it cannot match the account name or email address.
6. Verify the email address using the code AWS sends.
7. Choose **Personal** or **Business**, then enter the contact name, phone number, and address. For a business account, use a company phone number rather than a personal one.
8. Enter a valid credit or debit card. AWS places a small temporary authorization on the card, typically around 1 USD, to verify it. The authorization is reversed.
9. Complete identity verification by requesting a code by SMS or voice call and entering it.
10. Choose an account plan when prompted. Section 7.2 explains the difference between the Free and Paid plans.
11. Select the **Basic** support plan, which is free. Support plans can be changed at any time and are covered in section 15.7.
12. Wait for account activation. This is usually a few minutes but can take longer.
13. Sign in at https://console.aws.amazon.com using the root email address and password.

Do not stop here. Go straight to section 7.3 and secure the root user before creating anything.

---
## 7.2 How the AWS Free Tier Works

AWS restructured the Free Tier on July 15, 2025. Most study material still describes the old model, so it is worth being precise about which applies to you.

### 7.2.1 Accounts Created On or After July 15, 2025

At sign-up you choose between a **Free account plan** and a **Paid account plan**.

- Both plans receive 100 USD in credits when the account is created.
- Both can earn up to a further 100 USD in credits by completing activities in the console, for a maximum of 200 USD.
- Credits expire 12 months from the date the account was opened.
- Both plans include access to more than 30 always-free services with monthly usage limits.
- The **Free account plan expires either 6 months after sign-up or when the credits are used up, whichever comes first.** It can be upgraded to the Paid plan at any time.
- Free plan accounts have only the Always Free offerings active. Paid plan accounts may also have service free trials active.
- Some services that would consume the credit balance immediately are not available on the Free plan.
- The new Free Tier is available in all AWS Regions except the AWS GovCloud (US) Regions and the China Regions.

The consequence worth understanding: on the Free plan the account itself has an expiry date. If you intend to keep the account beyond six months, plan to upgrade to the Paid plan before the Free plan ends.

### 7.2.2 Accounts Created Before July 15, 2025

These accounts keep the previous model, which had three parts:

- **12 month free tier** on selected services, starting from account creation. This included 750 hours per month of t2.micro or t3.micro EC2 instances, 5 GB of S3 Standard storage, and 750 hours per month of db.t2.micro, db.t3.micro, or db.t4g.micro RDS with 20 GB of storage.

- **Always free** offers with no expiry, such as 1 million AWS Lambda requests and 400,000 GB-seconds per month, and 25 GB of Amazon DynamoDB storage with 25 write and 25 read capacity units.

- **Short term trials** on individual services, starting when the service is first used.

### 7.2.3 Monitoring Free Tier Usage

1. Open the Billing and Cost Management console at https://console.aws.amazon.com/billing/.
2. Choose **Free tier** in the navigation pane to see current usage against each allowance.
3. Under **Preferences**, choose **Billing preferences**, then edit **Alert preferences** to set the email address that receives free tier usage alerts.

### 7.2.4 What Actually Generates Unexpected Charges

The free tier does not protect you from these, and they are the most common surprises:

- NAT gateways, which bill per hour simply for existing, whether traffic passes through them or not.
- Elastic IP addresses that are allocated but not associated with a running instance.
- EBS volumes and snapshots left behind after an instance is terminated.
- Data transfer out to the internet.
- Choosing a larger instance size than intended in a launch wizard.
- Running instances that are idle. An idle instance bills exactly the same as a busy one.

---
> Free tier allowances and the list of always free services change. Check https://aws.amazon.com/free for current figures before planning any work around them.

---
## 7.3 Securing the Root User and Enabling MFA

The root user has unrestricted access to everything in the account, including billing and account closure. It cannot be restricted by IAM policies. Treat it as a break glass credential.

### 7.3.1 Immediate Actions

1. Sign in to the console as the root user.
2. Open the account menu at the top right and choose **Security credentials**.
3. Under **Multi-factor authentication (MFA)**, choose **Assign MFA device**.
4. Enter a device name.
5. Choose a device type. AWS recommends phishing resistant options, meaning a passkey or a FIDO2 security key such as a hardware token. A TOTP authenticator application remains valid but offers weaker protection against phishing and replay.
6. Complete enrollment. For a TOTP app, scan the QR code and enter two consecutive codes. For a security key, follow the browser prompt.
7. Confirm the device now appears in the MFA list.
8. Under **Access keys**, confirm that no root access keys exist. If any do, delete them. There is no legitimate reason for a root access key.
9. Set the account's alternate contacts under **Account settings**, so billing, operations, and security notifications reach the right people.
10. Store the root credentials and the MFA recovery method somewhere secure and separate, such as a password manager or a sealed physical record.

[AWS has been progressively requiring MFA on root users, beginning with AWS Organizations management accounts. Check the current IAM documentation for whether enforcement applies to your account type.]

### 7.3.2 Rules for Root Use Afterwards

- Do not use the root user for daily work. Create an administrative identity in section 7.4 and use that.
- A small number of tasks genuinely require root, including changing the account name or email address, changing the support plan, closing the account, restoring an IAM policy that locked everyone out, and registering as a seller in AWS Marketplace.
- Never share root credentials, and never embed them in scripts or applications.

---
## 7.4 Sign In Paths

There are two ways humans sign in. Which one you should use depends on the environment.

| Control | Learning and development | Production |
| --- | --- | --- |
| MFA | Recommended | Required |
| IAM Identity Center | Recommended | Required |
| IAM user with console password | Acceptable | Should be disabled |
| Root user | Initial setup only | Emergencies only |
| Least privilege | Yes | Yes |
| Sign-in auditing | Yes | Yes |

### 7.4.1 IAM User, Suitable for Learning

This is the simplest path and is what the labs in this course assume.

1. Sign in as root.
2. Open the **IAM** console.
3. Choose **User groups**, then **Create group**.
4. Name the group, for example `Administrators`.
5. Attach a permissions policy to the group. For a personal learning account, `AdministratorAccess` is appropriate. Attach policies to groups, never directly to users.
6. Choose **Create user group**.
7. Choose **Users**, then **Create user**.
8. Enter a user name.
9. Select **Provide user access to the AWS Management Console**.
10. Choose an autogenerated or custom password, and require a password reset at first sign-in.
11. Add the user to the group created above.
12. Choose **Create user**, then download or record the sign-in URL and credentials.
13. Sign out of the root user.
14. Sign in using the account-specific sign-in URL, which has the form `https://<account-id>.signin.aws.amazon.com/console`.
15. Assign an MFA device to this user as well, following the same steps as in section 7.3.1.

### 7.4.2 IAM Identity Center, Required for Production

AWS IAM Identity Center replaced AWS Single Sign-On in 2022 and is the recommended way to manage human access.

- Enabled from the management account of an AWS Organization.
- Identity source can be the built-in directory, Microsoft Active Directory, or an external provider such as Okta, Microsoft Entra ID, or Google Workspace.
- Users receive short-lived credentials rather than long-lived access keys, which removes the largest single category of credential leakage.
- Access is granted through permission sets mapped to accounts, so one identity can reach many accounts without a separate user in each.
- Where IAM users already exist in production, deactivate their console access under **IAM**, **Users**, the user, **Security credentials**, then confirm only Identity Center users can sign in.

Identity Center design is covered in section 17.5. For the labs in this course, an IAM user is sufficient.

---
## 7.5 Navigating the Management Console

The console home page is the starting point for everything in this course.

---
![AWS Management Console home page](https://github.com/user-attachments/assets/d0957d4a-4662-4ff3-bf5f-7278c076e3a0)

---

- **Search bar.** The fastest way to reach any service. Typing the service name beats browsing the services menu, and the search also covers features, documentation, AWS Marketplace listings, and your own resources.

---
![Console search bar returning service results](https://github.com/user-attachments/assets/341215b8-0350-4aee-a8dc-771ffe778110)

---

- **Services menu.** Browses the full service list grouped into categories such as Compute, Storage, and Networking and Content Delivery. Useful when you know the category but not the service name.

---
![Services menu showing AWS services grouped by category](https://github.com/user-attachments/assets/e6d011c0-7ae1-44ce-99ad-c71c48db443f)

---

- **Region selector.** The Region shown at the top right applies to almost everything you do. Resources created in one Region are invisible from another, which is the single most common source of confusion for new users. Check it before creating anything.

- **Global services.** IAM, Route 53, CloudFront, and billing are global and are unaffected by the Region selector.

- **Account menu.** Contains security credentials, account settings, billing, service quotas, and the account ID you will need for cross-account work.

- **Recently visited.** The console home page lists recently used services, which is usually faster than searching for the same three services repeatedly.

- **Favorites.** Pin frequently used services from the services menu so they appear in the left navigation.

- **Billing dashboard.** Monitors usage and cost and is where budget alerts are configured, as in section 7.8.

- **CloudShell.** The terminal icon in the top navigation opens a preauthenticated shell, covered in section 35.5.

### 7.5.1 Switching Roles

Role switching assumes a role in the same or another account, and is how cross-account access works day to day. Role design is covered in section 17.4.

1. Choose the account name at the top right.
2. Choose **Switch role**.
3. Enter the target account ID.
4. Enter the role name.
5. Optionally set a display name and color, which makes it obvious at a glance which account you are working in.
6. Choose **Switch Role**.
7. Confirm the active role now shows at the top of the console.

---

## 7.6 Lab: Create a Key Pair in the Console

**What a key pair is.** A key pair consists of a public key that AWS stores and a private key that you download. Linux instances use it for SSH authentication. Windows instances use it to decrypt the Administrator password for RDP access.

**Prerequisites**

- An AWS account with permission to manage EC2 resources.
- A web browser.

### 7.6.1 Procedure

1. Sign in to the AWS Management Console.
2. Check the Region selector at the top right and select the Region you intend to work in. Key pairs are Region-specific.
3. Type `EC2` in the console search bar and select **EC2**.
4. In the left navigation pane, scroll to the **Network & Security** section.
5. Choose **Key Pairs**.
6. Choose **Create key pair** at the top right.
7. In **Name**, enter a descriptive name, for example `MyLabKeyPair`.
8. Under **Key pair type**, choose one:
   - **RSA** works with both Linux and Windows instances. AWS generates 2048-bit SSH-2 RSA keys.
   - **ED25519** offers stronger security and better performance but is supported for Linux instances only. Do not choose it if you intend to connect to Windows.
9. Under **Private key file format**, choose one:
   - **.pem** for OpenSSH clients on Linux, macOS, and Windows 10 or 11 with built-in OpenSSH.
   - **.ppk** for PuTTY on Windows.
10. Optionally add tags to help with organization and cost allocation.
11. Choose **Create key pair**.

---
![Create key pair dialog in the EC2 console showing name, key pair type, and private key file format](https://github.com/user-attachments/assets/fd839172-daec-4ea9-b06a-c95e66a971bc)

---

12. Note that the private key file downloads automatically. This is the only time it is available.
13. Move the downloaded file out of the Downloads folder to a secure location.
14. Restrict the file permissions before use.
    - On Linux or macOS:
      ```sh
      chmod 400 /path/to/MyLabKeyPair.pem
      ```
      Verify with `ls -l MyLabKeyPair.pem`.
    - On Windows PowerShell, first remove inherited permissions:
      ```powershell
      icacls.exe "C:\path\to\MyLabKeyPair.pem" /inheritance:r
      ```
      Then grant read access to the current user only:
      ```powershell
      icacls.exe "C:\path\to\MyLabKeyPair.pem" /grant:r "$($env:USERNAME):R"
      ```
      Verify with `Get-Acl MyLabKeyPair.pem`.
15. Confirm the new key pair now appears in the **Key Pairs** list.

**Important.** AWS does not retain your private key. If the file is lost it cannot be recovered, and you will need to create a new key pair and replace it on any instance that used it.

### 7.6.2 Recommendations

- Keep the private key private. Never commit it to source control and never share it.
- Keep a backup in an encrypted location such as a password manager or an encrypted drive.
- Name keys so the project, environment, and owner are obvious, for example `prod-web-david`.
- Use RSA for anything that may involve Windows instances.
- For PuTTY, use the `.ppk` file directly, or convert a `.pem` file with PuTTYgen.

### 7.6.3 Cleanup

1. Open **EC2**, then **Key Pairs**.
2. Select the key pair.
3. Choose **Actions**, then **Delete**.
4. Type the confirmation text and confirm.
5. Delete the local private key file if it is no longer needed.

---
## 7.7 Lab: Create a Security Group in the Console

**What a security group is.** A security group is a set of rules controlling inbound and outbound traffic for AWS resources, most commonly EC2 instances. It is stateful, meaning a reply to an allowed inbound request is automatically permitted outbound. Each security group belongs to one VPC and can only be used within that VPC. Security groups are compared with network ACLs in section 9.5.

**Prerequisites**

- An AWS account with permission to manage EC2 resources.
- A web browser.

### 7.7.1 Procedure

1. Sign in to the AWS Management Console.
2. Confirm the Region selector shows the Region you intend to work in.
3. Type `EC2` in the console search bar and select **EC2**.
4. In the left navigation pane, scroll to the **Network & Security** section.
5. Choose **Security Groups**.
6. Choose **Create security group** at the top right.
7. In **Security group name**, enter a clear name, for example `MyLabSG`. The name and description cannot be changed after creation.
8. In **Description**, state the purpose, for example `Allow SSH and HTTP access for lab`.
9. In **VPC**, select the VPC this security group will belong to. If you have not created one, select the default VPC.
10. Under **Inbound rules**, choose **Add rule**.
11. Set **Type** to `SSH`. The protocol and port 22 fill in automatically.
12. Set **Source** to **My IP**. Never use `0.0.0.0/0` for SSH or RDP.
13. Choose **Add rule** again.
14. Set **Type** to `HTTP`. The protocol and port 80 fill in automatically.
15. Set **Source** to `0.0.0.0/0` for public web traffic. If the VPC is IPv6 enabled, add a second HTTP rule with source `::/0`.
16. Review the **Outbound rules** section. New security groups include a default rule allowing all outbound traffic. Leave it in place unless the workload requires egress filtering.

---
![Security group creation page showing inbound and outbound rule configuration](https://github.com/user-attachments/assets/1b68c02d-c2b8-4e4d-aa32-dfdf0a3f7219)

---
17. Optionally add tags.
18. Check the name, description, VPC, and every rule before continuing.
19. Choose **Create security group**.

---
![Security groups list showing the newly created security group](https://github.com/user-attachments/assets/87c9a320-5734-4224-a4e2-e15504fb49d5)

---

20. Confirm the new security group appears in the list with the expected VPC and rule count.

### 7.7.2 Attaching the Security Group to an Instance

For a new instance:

1. In the launch instance wizard, open the **Network settings** section.
2. Choose **Select existing security group**.
3. Select the security group.

For an existing instance:

1. Open **EC2**, then **Instances**.
2. Select the instance.
3. Choose **Actions**, then **Security**, then **Change security groups**.
4. Add or remove security groups.
5. Choose **Save**.

### 7.7.3 Recommendations

- Restrict SSH and RDP to a specific source address. Opening them to the world is the most common cause of compromised instances.
- Open only the ports the application actually needs.
- Choose names that reflect role and environment, since they cannot be renamed.
- Add `::/0` alongside `0.0.0.0/0` for public rules when the VPC is IPv6 enabled, or IPv6 clients will fail silently.
- Review security groups periodically and remove rules that are no longer needed.

### 7.7.4 Cleanup

1. Detach the security group from any instance still using it. A security group in use cannot be deleted.
2. Open **EC2**, then **Security Groups**.
3. Select the security group.
4. Choose **Actions**, then **Delete security groups**.
5. Confirm the deletion.

---
## 7.8 Lab Cost Discipline

Every lab in this course creates billable resources. Two habits prevent the bill that ends most people's AWS study.

**Set one budget alert now, before the first lab.**

1. Open the Billing and Cost Management console.
2. Choose **Budgets**, then **Create budget**.
3. Choose **Cost budget**.
4. Set the period to **Monthly** and enter a small budgeted amount, for example 5 USD.
5. Add an alert threshold at 80% of the budgeted amount.
6. Enter the email address that should receive the alert.
7. Create the budget.

The full billing toolset, including Cost Explorer, cost allocation tags, and Cost Anomaly Detection, is covered in Chapter 15.

**Tear down every lab when you finish it.**

- Follow the cleanup section at the end of each lab. Every lab in this course has one.
- Check the EC2 console for running instances, unattached EBS volumes, and unassociated Elastic IP addresses.
- Check for NAT gateways, which are the most expensive thing a beginner leaves running by accident.
- Work in one Region. Resources abandoned in a Region you have forgotten about will keep billing quietly.

---
## 7.9 End of Chapter Questions

**Q1.** Which task requires signing in as the AWS account root user?

- A. Launching an EC2 instance
- B. Creating an IAM user group
- C. Closing the AWS account
- D. Creating an S3 bucket

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* Closing the account is one of a small set of tasks that cannot be delegated to an IAM identity, along with changing the account email address and changing the support plan.

**Q2.** What is the recommended first action after creating a new AWS account?

- A. Create an S3 bucket for backups
- B. Enable MFA on the root user and stop using root for daily work
- C. Purchase a Business support plan
- D. Request a service quota increase

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* The root user cannot be restricted by IAM policy, so protecting it with MFA and moving daily work to a separate identity is the highest-value first step.

**Q3.** A student creates an AWS account today and selects the Free account plan. What happens at the end of the plan period?

- A. The account converts automatically to pay-as-you-go billing and continues
- B. The plan ends six months after sign-up or when the free tier credits are used up, whichever comes first, and the account must be upgraded to the Paid plan to continue
- C. The account receives another 100 USD in credits
- D. All resources are converted to always-free equivalents

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* For accounts created on or after July 15, 2025, the Free plan is time-bounded and credit-bounded, and upgrading to the Paid plan is an explicit action.

**Q4.** A security group is created with an inbound rule allowing SSH from `0.0.0.0/0`. Why is this a problem?

- A. Security groups cannot use CIDR notation for SSH
- B. It exposes port 22 to every address on the internet, which invites credential attacks against the instance
- C. Outbound traffic will be blocked by default
- D. The rule will prevent the instance from launching

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* SSH and RDP should be restricted to known source addresses; `0.0.0.0/0` is appropriate only for public services such as HTTP and HTTPS.

**Q5.** An organization is designing human access to 15 AWS accounts for 200 staff. Which approach should be recommended?

- A. Create IAM users with console passwords in each account
- B. Share a set of IAM user credentials per team
- C. Use IAM Identity Center with permission sets mapped to accounts, backed by the corporate identity provider
- D. Give each engineer root credentials for the accounts they work in

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Identity Center issues short-lived credentials from a single identity source and maps access to many accounts through permission sets, which avoids maintaining 3,000 separate IAM users.

**Q6.** An engineer creates an EC2 key pair, downloads the `.pem` file, and later deletes it from the laptop. What is the consequence?

- A. The key can be downloaded again from the EC2 console
- B. AWS can recover the private key through a support case
- C. The private key is unrecoverable, and any instance relying on it will need a new key pair configured
- D. The instance automatically switches to password authentication

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* AWS stores only the public key, so a lost private key cannot be regenerated or retrieved.