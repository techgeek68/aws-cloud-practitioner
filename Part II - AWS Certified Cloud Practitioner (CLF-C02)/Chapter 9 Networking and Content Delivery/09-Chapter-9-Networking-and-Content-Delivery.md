# Chapter 9: Networking and Content Delivery

---
Networking is where most AWS troubleshooting time goes, and it is the foundation for almost everything in Part III. This chapter defines the components. Chapters 21 and 22 design with them.

---
## 9.1 Networking Basics

**IP addressing**

- **IPv4** uses 32-bit addresses written as four octets, for example `192.0.2.1`.

- **IPv6** uses 128-bit addresses written as eight hexadecimal groups, for example `2001:0db8:85a3:0000:0000:8a2e:0370:7334`.

**CIDR notation**

Classless Inter-Domain Routing notation expresses an address range as `address/prefix`, where the prefix is the number of leading bits that identify the network.

- `192.0.2.0/24` covers 256 addresses, `192.0.2.0` through `192.0.2.255`.

- A smaller prefix means a larger range. `/16` is 65,536 addresses; `/28` is 16.

- Each single bit reduction in the prefix doubles the range.

| Prefix | Total addresses | Common use in AWS |
| --- | --- | --- |
| /16 | 65,536 | The largest permitted VPC |
| /20 | 4,096 | A generous subnet |
| /24 | 256 | A typical subnet |
| /28 | 16 | The smallest permitted VPC or subnet |

**The OSI model**

Seven layers describing how communication works, from Physical and Data Link at the bottom, through Network, Transport, and Session, to Presentation and Application at the top. The two that matter most here are layer 4, where security groups and Network Load Balancers operate on protocol and port, and layer 7, where Application Load Balancers and AWS WAF operate on HTTP content.

---
## 9.2 Amazon VPC Fundamentals

An Amazon VPC is a logically isolated section of the AWS Cloud in which you define your own network: IP ranges, subnets, route tables, and gateways.

### 9.2.1 VPCs and Subnets

- A VPC is dedicated to one AWS account, exists in exactly one Region, and spans every Availability Zone in that Region.

- A subnet partitions the VPC address space and belongs to exactly one Availability Zone. Covering two zones means creating two subnets.

- A subnet is **public** if its route table sends `0.0.0.0/0` to an internet gateway, and **private** if it does not. Nothing else distinguishes them. There is no "public" checkbox.

- Every VPC and every subnet must have a CIDR block.

### 9.2.2 IP Addressing

- The VPC IPv4 CIDR block may be between `/16` and `/28`.

- Up to four secondary CIDR blocks can be added after creation, and no blocks within a VPC may overlap.

- Subnet CIDR blocks come out of the VPC range and cannot overlap each other.

- IPv6 is supported, with subnet prefixes from `/44` to `/64` in increments of `/4`.

- Choose the range with room to grow. Resizing a VPC later is possible only by adding secondary CIDR blocks, and overlapping ranges make peering and VPN connections impossible.

### 9.2.3 Reserved IP Addresses

AWS reserves five addresses in every subnet:

| Address | Purpose |
| --- | --- |
| First address | Network address |
| First address + 1 | VPC router |
| First address + 2 | DNS server |
| First address + 3 | Reserved for future use |
| Last address | Broadcast address, reserved even though VPCs do not support broadcast |

In a `10.0.0.0/24` subnet, that leaves `10.0.0.4` through `10.0.0.254`, which is 251 usable addresses out of 256. The practical consequence is that a `/28` subnet has 11 usable addresses, not 16, which is easy to exhaust.

### 9.2.4 Public IP Address Types

- **Auto-assigned public IPv4.** Set at subnet level and overridable per instance. The address is released when the instance stops and a different one is assigned on start, so it is unsuitable for anything referenced by DNS.

- **Elastic IP.** Allocated to your account and remapped between instances or network interfaces at will. It survives stop and start, which is what makes it useful for a fixed endpoint or for a NAT gateway. An Elastic IP that is allocated but not associated with a running resource is billed.

---
## 9.3 Elastic Network Interfaces and Route Tables

**Elastic network interface (ENI)**

- A virtual network card that can be attached to and detached from instances.

- Carries a private IP, optionally a public or Elastic IP, one or more security groups, and a MAC address.

- Every instance has at least one ENI, created at launch and deleted with the instance.

- Additional ENIs allow an instance to sit in more than one subnet, or allow traffic to be moved to a standby instance by detaching and reattaching an interface rather than rebuilding anything.

**Route tables**

- A set of routes determining where traffic from a subnet is sent, matched by destination CIDR.

- Every subnet is associated with exactly one route table. Subnets with no explicit association use the VPC main route table.

- Every route table contains a `local` route covering the VPC CIDR. It cannot be removed or overridden, which is why all subnets in a VPC can always reach each other.

- The most specific matching route wins. A route for `10.0.5.0/24` takes precedence over `0.0.0.0/0`.

---
## 9.4 VPC Connectivity Options

These are named and defined here. Chapters 21 and 22 cover designing with them.

- **Internet gateway.** Attached to a VPC to allow resources with public addresses to reach the internet in both directions. Horizontally scaled and highly available by design, with nothing to size or manage.

- **NAT gateway.** A managed service allowing instances in private subnets to make outbound connections while remaining unreachable from the internet. It is placed in a public subnet, requires an Elastic IP, and is zonal, so high availability means one per Availability Zone. It bills per hour and per gigabyte processed, and is the resource most commonly left running by accident.

- **VPC peering.** A private connection between two VPCs, in the same Region or across Regions and accounts. Requires non-overlapping CIDR blocks and is not transitive: if A peers with B and B peers with C, A cannot reach C.

- **VPC endpoints.** Private connectivity to AWS services without traversing the internet. **Gateway endpoints** serve Amazon S3 and DynamoDB and work through a route table entry, at no charge. **Interface endpoints**, powered by AWS PrivateLink, serve most other services through an ENI in your subnet and are billed hourly and per gigabyte.

- **AWS Transit Gateway.** A central hub connecting many VPCs, VPN connections, and Direct Connect gateways, which removes the mesh of peering connections that becomes unmanageable past a handful of VPCs.

- **AWS Site-to-Site VPN.** An encrypted tunnel between a VPC and an on-premises network over the public internet. Quick to establish, with throughput and latency subject to internet conditions.

- **AWS Direct Connect.** A dedicated physical connection between an on-premises network and AWS, offering consistent latency and higher bandwidth than VPN, with a longer lead time to provision.

- **VPC sharing.** Sharing subnets from one VPC with other accounts in the same organization, so networking is managed centrally without duplicating it per account.

---
## 9.5 VPC Security Controls

### 9.5.1 Security Groups

- Operate at the level of the **elastic network interface**, so effectively at the instance level.

- **Stateful.** A response to an allowed inbound request is permitted outbound automatically, and the reverse. You do not write return path rules.

- **Allow rules only.** There is no way to express a deny. Traffic not matching any allow rule is dropped.

- Default behavior for a new security group is to deny all inbound and allow all outbound.

- All rules are evaluated together, and any matching allow is sufficient.

- A rule's source can be a CIDR range or **another security group**, which is how you express "the database accepts traffic from the application tier" without hardcoding addresses.

### 9.5.2 Network ACLs

- Operate at the **subnet** level, applying to every resource in the subnet.
- **Stateless.** Each packet is evaluated independently, so return traffic needs its own rule, normally allowing the ephemeral port range 1024 to 65535.
- Support both **allow and deny** rules, which makes them the tool for blocking a specific address.
- Rules are numbered and evaluated in ascending order, and the first match wins. Later rules are not considered.
- The default network ACL allows all inbound and outbound traffic. A custom network ACL denies everything until rules are added.

### 9.5.3 Comparison

| Attribute | Security group | Network ACL |
| --- | --- | --- |
| Scope | Network interface, effectively the instance | Subnet |
| Rule types | Allow only | Allow and deny |
| State | Stateful | Stateless |
| Evaluation | All rules considered together | Lowest-numbered match wins |
| Default | Deny inbound, allow outbound | Allow all, for the default ACL |
| Typical use | The primary access control | A coarse secondary layer, or blocking a specific address |

The working guidance: **use security groups as the main control and reach for network ACLs only when you need an explicit deny or a subnet-wide blanket rule.** Trying to manage fine-grained access through network ACLs produces rules that are hard to reason about and easy to break.

### 9.5.4 Applying This to a Design

Consider a small business hosting a website on EC2 with a private database, required to be highly available and defended in depth.

- Web servers go in public subnets in two Availability Zones, with a route to the internet gateway.
- Database instances go in private subnets in the same two zones, with no route to the internet gateway.
- A NAT gateway in each public subnet lets the database and application hosts fetch patches outbound.
- The web tier security group allows HTTP and HTTPS from the internet.
- The database security group allows the database port **from the web tier security group only**, not from a CIDR range.
- Network ACLs sit underneath as a coarse second layer.

That shape recurs throughout Part III and is built in the lab in section 9.8.

---
## 9.6 Amazon Route 53

Amazon Route 53 is AWS's DNS service, translating names into addresses and steering traffic. It is global, not Regional.

**Capabilities**

- Authoritative DNS hosting for public and private hosted zones. A private hosted zone resolves only inside associated VPCs.
- Domain registration.
- Health checks that monitor an endpoint and remove it from responses when it fails.
- Support for both IPv4 and IPv6 records.
- **Alias records**, an AWS-specific record type pointing at AWS resources such as load balancers, CloudFront distributions, and S3 website endpoints. Alias records work at the zone apex, where CNAME records are not permitted, and are not charged for queries.

**Routing policies**

| Policy | Behavior | Typical use |
| --- | --- | --- |
| Simple | One record, one or more values, no health checking | A single endpoint |
| Weighted | Splits traffic by assigned weights | Canary releases, A/B testing, gradual migration |
| Latency-based | Sends the user to the Region with the lowest latency for them | Multi-Region deployments optimizing performance |
| Failover | Primary until it fails a health check, then secondary | Active/passive disaster recovery |
| Geolocation | Routes by the user's country or continent | Content licensing, language, regulatory routing |
| Geoproximity | Routes by distance between user and resource, with an adjustable bias | Shifting load between Regions by geography |
| Multivalue answer | Returns up to eight healthy records at random | Simple load spreading with health checking |
| IP-based | Routes on the client's originating IP range | Steering a known ISP or corporate range |

The distinction examiners like: **latency-based** optimizes for measured performance, **geolocation** enforces where a user is, and **geoproximity** shifts load by distance with a deliberate bias. They are not interchangeable.

---
## 9.7 Amazon CloudFront

A content delivery network caches content close to users so requests do not travel to the origin every time.

**How it works**

- A **distribution** is the configuration unit. It has one or more **origins**, which can be an S3 bucket, a load balancer, an API Gateway endpoint, or any HTTP server, including one outside AWS.
- A request goes to the nearest **edge location**. On a cache hit it is served immediately. On a miss it goes to the **regional edge cache**, and only then to the origin.
- **Cache behaviors** map URL path patterns to origins and to caching rules, so `/images/*` and `/api/*` can be handled differently in one distribution.
- **Time to live** controls how long an object stays cached. **Invalidations** remove objects before expiry.

**Benefits**

- **Performance.** Content is served from the edge, and requests that must reach the origin still travel most of the way over the AWS backbone rather than the public internet.
- **Security.** AWS Shield Standard is included, AWS WAF integrates directly, and TLS is supported end to end. Signed URLs and signed cookies restrict access to specific users.
- **Origin offload.** Cached responses never reach the origin, which reduces both load and data transfer cost.
- **Programmability.** CloudFront Functions handle lightweight request manipulation at the edge; Lambda@Edge handles heavier logic. Both are covered in section 24.4.

**Pricing model**

- Charged on data transfer out to the internet, on requests, and on optional features such as field-level encryption and real-time logs.
- The first 1,000 invalidation paths per month are free.
- CloudFront has an always-free tier allowance that is not limited to the first 12 months of an account.

[CloudFront edge location counts, free tier allowances, and available pricing plans change frequently, and the two source documents merged into this course disagreed on the edge location count. Check the CloudFront pricing and features pages for current figures rather than any number printed in study material.]

---
## 9.8 Lab: Build a VPC and Launch a Web Server

**Objectives**

- Create a VPC with public and private subnets across two Availability Zones.
- Configure an internet gateway, a NAT gateway, and route tables.
- Create a security group allowing web traffic.
- Launch an Apache web server on EC2 and verify it from a browser.

**Architecture**

```
                         Internet
                            |
                       0.0.0.0/0
                            |
                      +-----+-----+
                      |  lab-igw  |   Internet Gateway
                      +-----+-----+
                            |
           +----------------+----------------+
           |                                 |
  +--------+--------+             +----------+------+
  | Public Subnet 1 |             | Public Subnet 2 |
  | 10.0.0.0/24     |             | 10.0.2.0/24     |
  | us-east-1a      |             | us-east-1b      |
  | NAT Gateway     |             | EC2: MyApp      |
  | (lab-nat-gw)    |             | (Apache/PHP)    |
  +-----------------+             | SG: Allow 80    |
           |                      +-----------------+
           |
  (lab-public-rt: 0.0.0.0/0 > lab-igw)
           |
  +--------+--------+             +-----------------+
  | Private Subnet 1|             | Private Subnet 2|
  | 10.0.1.0/24     |             | 10.0.3.0/24     |
  | us-east-1a      |             | us-east-1b      |
  +-----------------+             +-----------------+

  (lab-private-rt: 0.0.0.0/0 > lab-nat-gw)
```

---
![Full architecture diagram of the lab VPC](<images/Web server deployment in vpc.png>)

---
> Cost warning: The NAT gateway in this lab bills per hour from the moment it is created, and it is not covered by the free tier. Complete the lab in one sitting and follow the cleanup in section 9.8.11.

---
### 9.8.1 Step 1: Set the Region

1. Sign in to the AWS Management Console.
2. Open the Region selector at the top right and choose **US East (N. Virginia)**, `us-east-1`.
3. Confirm the Region indicator reads **N. Virginia** before continuing.

### 9.8.2 Step 2: Create the VPC

1. Type `VPC` in the search bar and open the **VPC** console.
2. Choose **Your VPCs** in the navigation pane.
3. Choose **Create VPC**.
4. Under **Resources to create**, select **VPC only**.
5. In **Name tag**, enter `lab-vpc`.
6. In **IPv4 CIDR block**, enter `10.0.0.0/16`.
7. Leave **IPv6 CIDR block** set to **No IPv6 CIDR block**.
8. Leave **Tenancy** set to **Default**.
9. Choose **Create VPC**.
10. Confirm the VPC appears with state **Available**.

### 9.8.3 Step 3: Create the Four Subnets

1. Choose **Subnets** in the navigation pane.
2. Choose **Create subnet**.
3. Select `lab-vpc` as the VPC.
4. In **Subnet name**, enter `Public Subnet 1`.
5. Set **Availability Zone** to `us-east-1a`.
6. Set **IPv4 subnet CIDR block** to `10.0.0.0/24`.
7. Choose **Create subnet**.
8. Select `Public Subnet 1`, choose **Actions**, then **Edit subnet settings**.
9. Select **Enable auto-assign public IPv4 address**, then choose **Save**.
10. Choose **Create subnet** again.
11. Select `lab-vpc`, name the subnet `Private Subnet 1`, set the Availability Zone to `us-east-1a`, and set the CIDR to `10.0.1.0/24`.
12. Choose **Create subnet**. Leave auto-assign public IPv4 disabled.
13. Choose **Create subnet** again.
14. Select `lab-vpc`, name the subnet `Public Subnet 2`, set the Availability Zone to `us-east-1b`, and set the CIDR to `10.0.2.0/24`.
15. Choose **Create subnet**.
16. Select `Public Subnet 2`, choose **Actions**, then **Edit subnet settings**, enable auto-assign public IPv4, and choose **Save**.
17. Choose **Create subnet** again.
18. Select `lab-vpc`, name the subnet `Private Subnet 2`, set the Availability Zone to `us-east-1b`, and set the CIDR to `10.0.3.0/24`.
19. Choose **Create subnet**. Leave auto-assign public IPv4 disabled.
20. Confirm all four subnets are listed against `lab-vpc`.

### 9.8.4 Step 4: Create and Attach the Internet Gateway

1. Choose **Internet gateways** in the navigation pane.
2. Choose **Create internet gateway**.
3. In **Name tag**, enter `lab-igw`.
4. Choose **Create internet gateway**.
5. Select `lab-igw`, choose **Actions**, then **Attach to VPC**.
6. Select `lab-vpc`.
7. Choose **Attach internet gateway**.
8. Confirm the state now reads **Attached**.

### 9.8.5 Step 5: Allocate an Elastic IP

1. Choose **Elastic IP addresses** in the navigation pane.
2. Choose **Allocate Elastic IP address**.
3. Set **Network border group** to `us-east-1`.
4. Add a tag with key `Name` and value `lab-nat-eip`.
5. Choose **Allocate**.

### 9.8.6 Step 6: Create the NAT Gateway

1. Choose **NAT gateways** in the navigation pane.
2. Choose **Create NAT gateway**.
3. In **Name**, enter `lab-nat-gw`.
4. Set **Subnet** to `Public Subnet 1`. A NAT gateway must sit in a public subnet.
5. Set **Connectivity type** to **Public**.
6. For **Elastic IP allocation ID**, select `lab-nat-eip`.
7. Choose **Create NAT gateway**.
8. Wait until the status shows **Available**. This takes a few minutes, and the next steps will not work until it does.

### 9.8.7 Step 7: Create the Public Route Table

1. Choose **Route tables** in the navigation pane.
2. Choose **Create route table**.
3. In **Name**, enter `lab-public-rt`.
4. Select `lab-vpc`.
5. Choose **Create route table**.
6. With `lab-public-rt` selected, open the **Routes** tab.
7. Choose **Edit routes**.
8. Choose **Add route**.
9. Set **Destination** to `0.0.0.0/0`.
10. Set **Target** to **Internet Gateway**, then select `lab-igw`.
11. Choose **Save changes**.
12. Open the **Subnet associations** tab.
13. Under **Subnets without explicit associations**, choose **Edit subnet associations**.
14. Select **Public Subnet 1** and **Public Subnet 2**.
15. Choose **Save associations**.

### 9.8.8 Step 8: Create the Private Route Table

1. Choose **Create route table**.
2. In **Name**, enter `lab-private-rt`.
3. Select `lab-vpc`.
4. Choose **Create route table**.
5. With `lab-private-rt` selected, open the **Routes** tab.
6. Choose **Edit routes**.
7. Choose **Add route**.
8. Set **Destination** to `0.0.0.0/0`.
9. Set **Target** to **NAT Gateway**, then select `lab-nat-gw`.
10. Choose **Save changes**.
11. Open the **Subnet associations** tab.
12. Choose **Edit subnet associations**.
13. Select **Private Subnet 1** and **Private Subnet 2**.
14. Choose **Save associations**.

### 9.8.9 Step 9: Verify the VPC Layout

1. Choose **Your VPCs** in the navigation pane.
2. Select `lab-vpc`.
3. Open the **Resource map** tab.
4. Confirm both public subnets route to the internet gateway and both private subnets route through the NAT gateway.

---
![VPC resource map showing subnets and route table associations](https://github.com/user-attachments/assets/e7cc1a63-5925-46c0-abeb-d9c0450e6391)

---
![VPC resource map showing the public subnet route to the internet gateway](https://github.com/user-attachments/assets/a90e0d07-3b14-450d-8945-37506825595f)

---
![VPC resource map showing the private subnet route to the NAT gateway](https://github.com/user-attachments/assets/8fc6033b-08bc-4c0f-9ad1-4e6c84581bf0)

---
![VPC resource map showing all four subnets and their route tables](https://github.com/user-attachments/assets/d0fd89a9-438a-4651-81a6-1b3d336e0f6f)

---
### 9.8.10 Step 10: Create the Security Group, Key Pair, and Web Server

**Create the security group**

1. Choose **Security groups** in the VPC console navigation pane.
2. Choose **Create security group**.
3. In **Security group name**, enter `Web Security Group`.
4. In **Description**, enter `Allow HTTP from anywhere`.
5. Set **VPC** to `lab-vpc`.
6. Under **Inbound rules**, choose **Add rule**.
7. Set **Type** to `HTTP` and **Source type** to **Anywhere-IPv4**.
8. Leave the outbound rules at the default, which allows all traffic.
9. Choose **Create security group**.

**Create the key pair**

10. Open the **EC2** console and choose **Key pairs**.
11. Choose **Create key pair**.
12. In **Name**, enter `MyLoginKey`.
13. Set **Key pair type** to **RSA**.
14. Set **Private key file format** to `.pem`, or `.ppk` if you will connect with PuTTY on Windows.
15. Choose **Create key pair** and store the downloaded file securely, as described in section 7.6.

**Launch the web server**

16. In the EC2 console, choose **Instances**, then **Launch instances**.
17. In **Name**, enter `MyApp`.
18. Under **Application and OS Images**, choose **Red Hat Enterprise Linux** and select the latest official RHEL HVM x86_64 image. If RHEL 10 is unavailable in your Region, the latest RHEL 9 image works with the same user data script.
19. Set **Instance type** to `t3.micro`.
20. Set **Key pair** to `MyLoginKey`.
21. Under **Network settings**, choose **Edit**.
22. Set **VPC** to `lab-vpc`.
23. Set **Subnet** to `Public Subnet 2 (us-east-1b)`.
24. Set **Auto-assign public IP** to **Enable**.
25. Under **Firewall**, choose **Select existing security group** and select `Web Security Group`.
26. Leave **Storage** at the default 10 GiB gp3 volume.
27. Expand **Advanced details** and scroll to **User data**.
28. Paste the script below.

    The script updates packages, installs Apache, PHP, MariaDB and supporting tools, enables and starts `firewalld` and `httpd`, opens port 80, sets the SELinux boolean that allows Apache to make outbound connections, and writes a PHP page that reads instance metadata using IMDSv2.

    ```bash
    #!/bin/bash
    set -euo pipefail

    sudo dnf -y update

    sudo dnf install -y httpd php php-mysqlnd mariadb-server curl jq firewalld policycoreutils-python-utils

    # Enable and start services
    sudo systemctl enable --now firewalld httpd mariadb || true
    sudo firewall-cmd --permanent --add-service=http
    sudo firewall-cmd --reload

    # Allow Apache to make outbound network connections, required for IMDSv2
    sudo setsebool -P httpd_can_network_connect on

    # Write the PHP metadata page using IMDSv2
    sudo tee /var/www/html/index.php > /dev/null <<'PHP'
    <?php
    function md($p) {
      $t = trim(shell_exec("curl -s -X PUT http://169.254.169.254/latest/api/token -H 'X-aws-ec2-metadata-token-ttl-seconds:60'"));
      if (!$t) return 'N/A';
      $v = trim(shell_exec("curl -s -H 'X-aws-ec2-metadata-token: $t' http://169.254.169.254/latest/meta-data/$p"));
      return $v ? htmlspecialchars($v) : 'N/A';
    }

    $meta = [
      'Instance ID'       => md('instance-id'),
      'Instance Type'     => md('instance-type'),
      'Availability Zone' => md('placement/availability-zone'),
      'Private IP'        => md('local-ipv4'),
      'Public Hostname'   => md('public-hostname'),
      'Public IPv4'       => md('public-ipv4'),
    ];
    ?>
    <!doctype html>
    <html lang="en">
    <head><meta charset="utf-8"><title>AWS Lab: Virtual Private Cloud</title></head>
    <body>
      <h1>AWS Lab: Virtual Private Cloud</h1>
      <table border="1" cellpadding="8">
      <?php foreach ($meta as $k => $v): ?>
        <tr><th align="left"><?= $k ?></th><td><?= $v ?></td></tr>
      <?php endforeach; ?>
      </table>
    </body>
    </html>
    PHP

    echo "user-data completed $(date)" | sudo tee /var/log/user-data-status.txt
    ```

29. Choose **Launch instance**.
30. Choose the instance ID in the confirmation banner to open its details.
31. Wait until **Instance state** shows **Running** and **Status checks** shows **3/3 checks passed**.
32. Note the **Public IPv4 address** and **Public IPv4 DNS** values in the **Details** pane.
33. Open a browser and go to `http://<Public-IPv4-DNS>`. Allow a minute or two after the status checks pass for the user data script to finish.
34. Confirm the page loads and displays live instance metadata.

![Lab web page displaying live EC2 instance metadata](https://github.com/user-attachments/assets/4e08fbb3-c800-4d86-a2f5-2a8c6e84cb9c)

**If the page does not load**, check these in order:

- `/var/log/cloud-init-output.log` for user data script errors.
- `/var/log/user-data-status.txt` for the completion marker.
- `/var/log/httpd/error_log` for Apache errors.
- `sudo ausearch -m AVC -ts recent` for SELinux denials.

### 9.8.11 Cleanup

Delete in this order to avoid dependency errors.

1. Open **EC2**, choose **Instances**, select `MyApp`, choose **Instance state**, then **Terminate instance**, and confirm.
2. Open **VPC**, choose **NAT gateways**, select `lab-nat-gw`, choose **Actions**, then **Delete NAT gateway**, and confirm. Wait until the status shows **Deleted**.
3. Choose **Internet gateways**, select `lab-igw`, choose **Actions**, then **Detach from VPC**, and confirm.
4. With `lab-igw` still selected, choose **Actions**, then **Delete internet gateway**, and confirm.
5. Choose **Subnets**, select all four lab subnets, choose **Actions**, then **Delete subnet**, and confirm.
6. Choose **Route tables**, select `lab-public-rt` and `lab-private-rt`, choose **Actions**, then **Delete route table**, and confirm. Do not delete the main route table.
7. Choose **Security groups**, select `Web Security Group`, choose **Actions**, then **Delete security groups**, and confirm.
8. Choose **Your VPCs**, select `lab-vpc`, choose **Actions**, then **Delete VPC**, and confirm.
9. Open **EC2**, choose **Elastic IPs**, select `lab-nat-eip`, choose **Actions**, then **Release Elastic IP addresses**, and confirm. An unassociated Elastic IP is billed, so do not skip this.
10. If the key pair is no longer needed, choose **Key pairs**, select `MyLoginKey`, choose **Actions**, then **Delete**.

### 9.8.12 Security Notes

- The `httpd_can_network_connect` SELinux boolean is needed only because the PHP page fetches live metadata. Replace the metadata with static values and it can stay disabled.
- The security group allows HTTP from any address, which is appropriate for a lab and not for production. A real deployment would terminate TLS on an Application Load Balancer or CloudFront and would not expose the instance directly.

---
## 9.9 End of Chapter Questions

**Q1.** Which AWS service lets you provision a logically isolated virtual network with your own IP ranges, subnets, and routing?

- A. AWS Config
- B. Amazon Route 53
- C. AWS Direct Connect
- D. Amazon VPC

**Answer: D.** *Target exam: AWS Certified Cloud Practitioner.* Config records resource configuration, Route 53 provides DNS, and Direct Connect provides a dedicated on-premises link; only VPC creates the network itself.

**Q2.** What is the default behavior of a newly created security group?

- A. Allows all inbound traffic and denies all outbound traffic
- B. Denies all inbound traffic and allows all outbound traffic
- C. Allows all inbound and all outbound traffic
- D. Denies all inbound and all outbound traffic

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* Inbound requires an explicit allow rule, while outbound is permitted by default until you restrict it.

**Q3.** How many IP addresses does AWS reserve in every subnet, and how many are usable in a `/24`?

- A. Two reserved, 254 usable
- B. Three reserved, 253 usable
- C. Five reserved, 251 usable
- D. Five reserved, 250 usable

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* AWS reserves the network address, the VPC router, the DNS server, one address for future use, and the broadcast address, leaving 251 of 256.

**Q4.** Instances in a private subnet must download operating system patches from the internet but must not be reachable from it. What should be configured?

- A. Attach an internet gateway to the private subnet
- B. Assign Elastic IP addresses to the instances
- C. Route `0.0.0.0/0` from the private subnet to a NAT gateway in a public subnet
- D. Add an inbound security group rule allowing HTTPS from `0.0.0.0/0`

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* A NAT gateway permits outbound-initiated connections while providing no path for inbound connections from the internet.

**Q5.** Which statement about network ACLs is correct?

- A. They are stateful and support allow rules only
- B. They are stateless, support both allow and deny rules, and evaluate rules in numerical order
- C. They apply to individual instances rather than subnets
- D. All rules are evaluated and the most permissive one applies

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Statelessness means return traffic needs its own rule, and the first matching rule by number decides the outcome.

**Q6.** An application is deployed in three Regions, and users should be sent to whichever gives them the best response time. Which Route 53 routing policy fits?

- A. Geolocation routing
- B. Weighted routing
- C. Latency-based routing
- D. Multivalue answer routing

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Latency-based routing uses measured network latency, whereas geolocation routes on where the user is rather than on how fast the path is.
