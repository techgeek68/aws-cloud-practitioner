# Chapter 13: Elasticity, Load Balancing, and Monitoring

---

## 13.1 Elastic Load Balancing

Elastic Load Balancing distributes incoming traffic across multiple targets, including EC2 instances, containers, IP addresses, and Lambda functions, across one or more Availability Zones. The load balancer scales itself as traffic changes.

### 13.1.1 Load Balancer Types

| Type | Layer | Use |
| --- | --- | --- |
| Application Load Balancer (ALB) | 7, HTTP and HTTPS | Web applications and microservices needing path-based, host-based, or header-based routing |
| Network Load Balancer (NLB) | 4, TCP, UDP, and TLS | Ultra-low latency and millions of requests per second; supports static and Elastic IP addresses |
| Gateway Load Balancer (GWLB) | 3, IP packets | Deploying and scaling third-party appliances such as firewalls and intrusion detection systems |
| Classic Load Balancer (CLB) | 4 and 7 | Legacy only. AWS recommends migrating to ALB or NLB, and new accounts cannot create one |

### 13.1.2 How It Works

- **Targets** are registered in **target groups** for ALB, NLB, and GWLB. The Classic Load Balancer registers instances directly.
- **Listeners** watch a port and protocol for incoming connections and forward traffic according to their rules.
- **Health checks** run continuously against each target, and the load balancer routes only to targets currently passing.

The health check is the part people underestimate. A load balancer with a health check pointing at a path that always returns 200, regardless of whether the application works, will happily send traffic to broken instances.

### 13.1.3 Monitoring a Load Balancer

- **CloudWatch metrics** cover request count, latency, error rates by class, and healthy and unhealthy host counts.
- **Access logs** record request-level detail and are delivered to Amazon S3. They are off by default.
- **CloudTrail** records API calls made to the load balancer's configuration, which is separate from the traffic it carries.

---

## 13.2 Amazon CloudWatch

This section owns the CloudWatch definitions for the course. Section 23.7 covers observability design, and section 34.3 covers the CLI operations.

### 13.2.1 What It Collects

- **Standard metrics** published automatically by AWS services.
- **Custom metrics** published by your own applications or by the CloudWatch agent. Memory and disk usage on EC2 are custom metrics, because the hypervisor cannot see inside the guest.
- **Logs** collected from AWS services and applications through CloudWatch Logs.
- **Events** matched by rules in Amazon EventBridge and routed to targets for automated response.
- **Dashboards** presenting metrics across services and accounts.

### 13.2.2 Alarms

An alarm evaluates a metric over a defined period and acts when the condition holds for a set number of consecutive evaluation periods. Alarms can be based on:

- **Static thresholds**, such as CPU utilization above 60%.
- **Anomaly detection**, which builds a baseline from history and alerts on deviation.
- **Metric math**, which computes across several metrics, for example an error rate as errors divided by requests.

An alarm has three states: `OK`, `ALARM`, and `INSUFFICIENT_DATA`. Actions can target Amazon SNS for notification, EC2 Auto Scaling for a scaling policy, or EC2 for stop, terminate, reboot, or recover.

**Example alarms**

- EC2: CPU utilization above 60% for 5 consecutive minutes.
- RDS: database connections above 10 for 1 minute.
- S3: bucket size above a defined threshold.
- ELB: healthy host count below 5 for 10 minutes.
- EBS: read operations above 1,000 in the evaluation period.

### 13.2.3 Basic and Detailed Monitoring

- **Basic monitoring** is free and publishes EC2 metrics every 5 minutes.
- **Detailed monitoring** publishes every 1 minute for an additional charge.

The distinction matters for scaling. A five-minute metric means an Auto Scaling group can be up to five minutes behind the load it is meant to be responding to, which is why the lab in section 13.8 enables detailed monitoring.

---

## 13.3 Amazon EC2 Auto Scaling

Without scaling you either overprovision and waste money at low demand, or underprovision and fail at peak. Auto Scaling aligns capacity with demand automatically, and replaces instances that fail their health checks.

### 13.3.1 Auto Scaling Groups

An Auto Scaling group is a managed collection of instances defined by three numbers:

- **Minimum capacity**, the floor that always runs.
- **Maximum capacity**, the ceiling that is never exceeded.
- **Desired capacity**, the number the group currently tries to maintain.

The group also spans a set of subnets, which is how it distributes instances across Availability Zones.

### 13.3.2 Scaling Methods

| Method | How it decides |
| --- | --- |
| Manual | You change the desired capacity yourself |
| Scheduled | Capacity changes at defined times, for predictable patterns |
| Dynamic, target tracking | Keeps a chosen metric at a target value, for example average CPU at 60% |
| Dynamic, step scaling | Changes capacity in increments based on how far a threshold has been breached |
| Dynamic, simple scaling | A single adjustment per alarm, with a cooldown between actions |
| Predictive | Forecasts demand from history using machine learning and provisions ahead of it |

Target tracking is the sensible default. You state the outcome you want and AWS creates and manages the alarms behind it.

### 13.3.3 Launch Templates and Launch Configurations

A launch template defines what new instances look like: AMI, instance type, key pair, IAM role, security groups, block device mappings, and network settings.

AWS recommends **launch templates** over the older launch configurations. Templates support versioning, allow On-Demand and Spot instances to be mixed in one request, and are required for newer Auto Scaling features. Launch configurations still function but receive no new capabilities.

### 13.3.4 Health Checks and Replacement

- **EC2 health checks** look at instance status checks.
- **ELB health checks** look at whether the load balancer considers the target healthy.

Turning on ELB health checks matters, because an instance can pass its EC2 status checks while the application on it has crashed. A **health check grace period** gives new instances time to boot and start the application before checks begin counting against them.

---

## 13.4 AWS Auto Scaling vs EC2 Auto Scaling

- **Amazon EC2 Auto Scaling** scales fleets of EC2 instances specifically.
- **AWS Auto Scaling** is a broader service giving one interface for scaling several resource types together: EC2 Auto Scaling groups, ECS tasks, DynamoDB tables and indexes, and Aurora read replicas. It is oriented toward maintaining performance at the lowest cost across an application's resources.

If the question mentions only EC2, it is EC2 Auto Scaling. If it mentions scaling DynamoDB or Aurora alongside EC2 from one place, it is AWS Auto Scaling.

---

## 13.5 Reliability and Availability

- **Reliability** is a system's ability to perform its intended function correctly and consistently over time. It is measured by mean time between failures; a higher MTBF means a more reliable system.
- **Availability** is the percentage of time a system is operational, calculated as uptime divided by total time.
- **High availability** is a design approach that minimizes downtime, ideally through automated mechanisms rather than someone being paged.

### 13.5.1 Availability Tiers

| Availability | Common name | Maximum annual downtime |
| --- | --- | --- |
| 99% | Two nines | About 87.6 hours |
| 99.9% | Three nines | About 8.7 hours |
| 99.99% | Four nines | About 52 minutes |
| 99.999% | Five nines | About 5 minutes |

Each additional nine costs considerably more than the last. Deciding which tier a workload actually needs is an architectural decision, not a default.

### 13.5.2 What Drives Availability

- **Fault tolerance**, achieved through redundancy such as multi-AZ deployment, so a component failure does not become an outage.
- **Recoverability**, defined by **recovery time objective**, the maximum acceptable downtime, and **recovery point objective**, the maximum acceptable data loss. Both are covered in section 29.4.
- **Scalability**, so that load increases do not become availability problems.

---

## 13.6 AWS Trusted Advisor

Trusted Advisor continuously evaluates an AWS environment against best practices and recommends actions across cost optimization, performance, resilience, security, operational excellence, and service limits.

**Check access by support plan**

- The **Basic** plan gives access to all checks in the Service Limits category and selected checks in the Security and Fault Tolerance categories. Automatic check updates are not included, so Security checks must be refreshed manually in the console.
- Full access to all Trusted Advisor checks and to the Trusted Advisor API requires an **AWS Business Support+**, **AWS Enterprise Support**, or **AWS Unified Operations** plan.

[The source notes stated 56 checks on all plans and 482 in total. Those counts are not published in the current AWS Support documentation and change as checks are added, so the qualitative access rules above are used instead.]

**Support plan changes, confirmed against AWS documentation**

- **Developer Support** will be discontinued on January 1, 2027.
- **Business Support** will be discontinued on January 1, 2027.
- **Enterprise On-Ramp** will be discontinued on January 1, 2027. Through 2026, Enterprise On-Ramp customers are automatically upgraded to Enterprise Support at contract renewal or in periodic batches, with an email notification a month beforehand.
- The replacement plans are **Business Support+**, at a $29 per month minimum per account, **Enterprise Support**, at a $5,000 per month minimum reduced from $15,000, and **Unified Operations**.
- Enterprise Support provides a designated technical account manager, 15-minute response for production-critical cases, and AWS Security Incident Response at no additional cost.
- Developer Support, Business Support, and Enterprise On-Ramp remain available in the AWS GovCloud (US) Regions.

This matters for the exam as well as for practice: study material written before this announcement lists the old plan names, and the CLF-C02 exam guide may lag the change. Learn both sets of names.

---

## 13.7 AWS Health Dashboard

- The **Service health** view shows the general status of AWS services by Region, and is public.
- The **Your account health** view shows events specific to your account and resources, such as scheduled maintenance on an instance you own or a service issue affecting a resource you are using.
- Events can be routed to EventBridge for automated response, which is how teams turn a maintenance notice into a ticket rather than an email someone misses.

The distinction from Trusted Advisor: Health tells you what AWS is doing that affects you. Trusted Advisor tells you what you are doing that you should change.

---

## 13.8 Lab: Load Balancing and Auto Scaling

This is the longest lab in Part II and the one that ties the chapter together. You will build a VPC, configure a web application that can generate its own CPU load, capture it as an AMI, put it behind an Application Load Balancer, and watch an Auto Scaling group respond to load and to a deliberately terminated instance.

**Cost warning.** An Application Load Balancer bills per hour and is not free tier eligible. The Auto Scaling group can reach six instances during the load test. Complete the lab in one sitting and follow the cleanup.

**Application files.** The six PHP files are provided alongside this chapter in `lab-files/13-alb-asg-app/`.

**Target architecture**

| Resource | Value |
| --- | --- |
| VPC | `ASB-Lab`, `10.0.0.0/16` |
| Public subnet A | `10.0.1.0/24`, `us-east-1a` |
| Public subnet B | `10.0.2.0/24`, `us-east-1b` |
| Private subnet A | `10.0.3.0/24`, `us-east-1a` |
| Private subnet B | `10.0.4.0/24`, `us-east-1b` |
| Auto Scaling | Minimum 1, desired 1, maximum 6 |
| Scaling target | 60% average CPU utilization |

### 13.8.1 Step 1: Create the VPC and Subnets

1. Open the **VPC** console and confirm the Region is **US East (N. Virginia)**.
2. Choose **Create VPC**.
3. Under **Resources to create**, select **VPC and more**.
4. Set **Name tag auto-generation** to `ASB-Lab`.
5. Set **IPv4 CIDR block** to `10.0.0.0/16`.
6. Set **IPv6 CIDR block** to **No IPv6 CIDR block**.
7. Set **Tenancy** to **Default**.
8. Set **Number of Availability Zones** to `2`.
9. Set **Number of public subnets** to `2`.
10. Set **Number of private subnets** to `2`.
11. Set **NAT gateways** to **None**. The Auto Scaling instances launch from a prebuilt AMI and need no outbound internet access, which keeps this lab considerably cheaper.
12. Set **VPC endpoints** to **None**.
13. Confirm **Enable DNS hostnames** and **Enable DNS resolution** are both selected.
14. Choose **Create VPC**.
15. Wait for every creation step to report success.

![VPC creation preview showing subnets and route tables](https://github.com/user-attachments/assets/bcee5c34-ab7c-4de0-bba5-e91d081cc240)

![VPC resource map after creation](https://github.com/user-attachments/assets/eeb7b4c9-fb9c-4fe7-9a68-66eed1d2cb5b)

### 13.8.2 Step 2: Create the Security Groups

**The load balancer group**

1. In the VPC console, choose **Security groups**, then **Create security group**.
2. Set **Security group name** to `ALB-SG`.
3. Set **Description** to `Allow inbound HTTP from the internet`.
4. Set **VPC** to `ASB-Lab-vpc`.
5. Add an inbound rule: **Type** `HTTP`, **Protocol** TCP, **Port** 80, **Source** `0.0.0.0/0`.
6. Leave the outbound rules at the default.
7. Choose **Create security group**.

**The instance group**

8. Choose **Create security group** again.
9. Set **Security group name** to `EC2-SG`.
10. Set **Description** to `Allow HTTP from ALB and SSH for administration`.
11. Set **VPC** to `ASB-Lab-vpc`.
12. Add an inbound rule: **Type** `HTTP`, **Port** 80, and set **Source** to the `ALB-SG` security group. Selecting the group rather than a CIDR range means only the load balancer can reach the instances.
13. Add an inbound rule: **Type** `SSH`, **Port** 22, **Source** **My IP**. The source notes used **Anywhere-IPv4** here; restrict it to your own address instead.
14. Leave the outbound rules at the default.
15. Choose **Create security group**.

### 13.8.3 Step 3: Launch and Configure the Initial Instance

1. Open the **EC2** console and choose **Launch instances**.
2. Set **Name** to `MyAppServer`.
3. Select the latest **Amazon Linux 2023** AMI, 64-bit x86.
4. Set **Instance type** to `t2.micro`.
5. Select an existing key pair or create and download a new one.
6. Next to **Network settings**, choose **Edit**.
7. Set **VPC** to `ASB-Lab-vpc`.
8. Set **Subnet** to `ASB-Lab-subnet-public1-us-east-1a`.
9. Set **Auto-assign public IP** to **Enable**.
10. Under **Firewall**, choose **Select existing security group** and select `EC2-SG`.
11. Expand **Advanced details** and set **Detailed CloudWatch monitoring** to **Enable**. This publishes metrics every minute instead of every five, so the scaling test responds in a reasonable time.
12. Scroll to **User data** and paste the following.

    ```bash
    #!/bin/bash
    dnf update -y
    dnf install -y httpd php-cli php-fpm php-mysqlnd php-common \
      php-gd php-xml php-opcache php-curl
    systemctl enable --now php-fpm httpd
    ```

13. Choose **Launch instance**.
14. Wait for **Running** with **3/3 checks passed**, then note the **Public IPv4 address**.

**Deploy the application**

15. Set permissions on the key file and connect.
    ```bash
    chmod 400 Your_Key_Pair.pem
    ssh -i "Your_Key_Pair.pem" ec2-user@<Public-DNS-or-IP>
    ```
16. Change to the web root.
    ```bash
    cd /var/www/html
    ```
17. Create each of the six files from `lab-files/13-alb-asg-app/`, using `sudo vi` or `sudo nano`.

    | File | Purpose |
    | --- | --- |
    | `health.php` | Returns HTTP 200 for the load balancer health check |
    | `util.php` | Shared helpers for instance metadata, CPU measurement, and load control |
    | `controller.php` | Background process that drives CPU load for a set duration |
    | `index.php` | The web page showing instance metadata and a CPU gauge |
    | `load.php` | Starts and stops the artificial load |
    | `load_api.php` | JSON endpoint the page polls for CPU and load state |

18. Set ownership so Apache can read the files.
    ```bash
    sudo chown -R apache:apache /var/www/html
    ```
19. Set permissions.
    ```bash
    sudo chmod -R 755 /var/www/html
    ```
20. Restart both services.
    ```bash
    sudo systemctl restart httpd php-fpm
    ```
21. Confirm both are running.
    ```bash
    sudo systemctl status httpd php-fpm
    ```
22. Open the public IP address of `MyAppServer` in a browser.
23. Confirm the EC2 metadata panel and the CPU gauge appear.
24. Confirm the health check endpoint responds by opening `http://<Public-IP>/health.php` and checking it returns `OK`.

### 13.8.4 Step 4: Create the AMI

Capturing the configured instance means the Auto Scaling group can launch identical copies without repeating any of the installation.

1. In **EC2**, choose **Instances** and select `MyAppServer`.
2. Choose **Actions**, then **Image and templates**, then **Create image**.
3. Set **Image name** to `AppServerAMI`.
4. Set **Description** to `Configured web application server for Auto Scaling lab`.
5. Leave **No reboot** cleared, so the instance reboots and the image is consistent.
6. Choose **Create image**.
7. Choose **AMIs** in the navigation pane.
8. Wait until the status changes from **Pending** to **Available**, typically two to five minutes.

You can continue to step 5 while it builds, but do not create the launch template until the AMI is **Available**.

### 13.8.5 Step 5: Create the Target Group and Load Balancer

**Target group**

1. Choose **Target Groups** under **Load Balancing**, then **Create target group**.
2. Set **Target type** to **Instances**.
3. Set **Target group name** to `LabTargetGroup`.
4. Set **Protocol** to `HTTP` and **Port** to `80`.
5. Set **VPC** to `ASB-Lab-vpc`.
6. Set **Protocol version** to `HTTP1`.
7. Set **Health check protocol** to `HTTP`.
8. Set **Health check path** to `/health.php`.
9. Expand **Advanced health check settings**.
10. Set **Healthy threshold** to `2`, **Unhealthy threshold** to `2`, **Timeout** to `5` seconds, **Interval** to `10` seconds, and **Success codes** to `200`.
11. Choose **Next**.
12. Do not register any targets. The Auto Scaling group registers them.
13. Choose **Create target group**.

**Load balancer**

14. Choose **Load Balancers**, then **Create load balancer**.
15. Under **Application Load Balancer**, choose **Create**.
16. Set **Load balancer name** to `ALB`.
17. Set **Scheme** to **Internet-facing**.
18. Set **IP address type** to **IPv4**.
19. Set **VPC** to `ASB-Lab-vpc`.
20. Under **Mappings**, select `us-east-1a` with `ASB-Lab-subnet-public1-us-east-1a` and `us-east-1b` with `ASB-Lab-subnet-public2-us-east-1b`. A load balancer requires at least two Availability Zones.
21. Under **Security groups**, remove `default` and attach `ALB-SG`.
22. Under **Listeners and routing**, set the protocol to `HTTP` on port `80` with the default action forwarding to `LabTargetGroup`.
23. Choose **Create load balancer**.
24. Wait until the state changes from **Provisioning** to **Active**.

### 13.8.6 Step 6: Create the Launch Template

1. Choose **Launch Templates**, then **Create launch template**.
2. Set **Launch template name** to `ALBConfig`.
3. Set **Template version description** to `V1.0`.
4. Select **Provide guidance to help me set up a template that I can use with EC2 Auto Scaling**.
5. Under **Application and OS Images**, choose **My AMIs**, then **Owned by me**, and select `AppServerAMI`.
6. Set **Instance type** to `t2.micro`.
7. Select the key pair used in step 3.
8. Under **Network settings**, select the `EC2-SG` security group. Do not set a subnet here; the Auto Scaling group determines placement.
9. Expand **Advanced details** and set **Detailed CloudWatch monitoring** to **Enable**.
10. Choose **Create launch template**.

### 13.8.7 Step 7: Create the Auto Scaling Group

1. Choose **Auto Scaling Groups**, then **Create Auto Scaling group**.
2. Set **Auto Scaling group name** to `ALB-Scaling-Group`.
3. Set **Launch template** to `ALBConfig` with version **Default**.
4. Choose **Next**.
5. Set **VPC** to `ASB-Lab-vpc`.
6. Under **Availability Zones and subnets**, select `ASB-Lab-subnet-private1-us-east-1a` and `ASB-Lab-subnet-private2-us-east-1b`. The instances sit in private subnets and are reachable only through the load balancer.
7. Set **Availability Zone distribution** to **Balanced best effort**.
8. Choose **Next**.
9. Under **Load balancing**, select **Attach to an existing load balancer**.
10. Select **Choose from your load balancer target groups** and select `LabTargetGroup | HTTP`.
11. Turn on **ELB health checks** in addition to the EC2 health checks that are on by default.
12. Set **Health check grace period** to `300` seconds, giving each instance time to boot and start Apache before checks count against it.
13. Choose **Next**.
14. Set **Desired capacity** to `1`, **Minimum capacity** to `1`, and **Maximum capacity** to `6`.
15. Under **Automatic scaling**, select **Target tracking scaling policy**.
16. Set **Scaling policy name** to `ASBScalingPolicy`.
17. Set **Metric type** to **Average CPU utilization**.
18. Set **Target value** to `60`.
19. Select **Enable group metrics collection within CloudWatch**.
20. Choose **Next**.
21. Choose **Add notification**.
22. Create a new SNS topic named `MyAppMonitor`.
23. Enter your email address as the recipient.
24. Select the event types **Launch**, **Terminate**, **Fail to launch**, and **Fail to terminate**.
25. Choose **Next**.
26. Add a tag with key `Name` and value `LoadBalancedAppServer`.
27. Choose **Next**, review the configuration, and choose **Create Auto Scaling group**.
28. Check your email and confirm the SNS subscription. Until you do, no notifications arrive.

### 13.8.8 Step 8: Verify the Deployment

1. Open the Auto Scaling group and review the **Activity** tab. One instance should be launching.

![Auto Scaling group activity showing the first instance launching](https://github.com/user-attachments/assets/1899b6b9-8943-4dd9-af1a-0730a6b0e3f4)

![Auto Scaling group details showing capacity settings](https://github.com/user-attachments/assets/36a82201-fc04-48a9-87ee-4e5e67f1782b)

![Instance management tab showing the running instance](https://github.com/user-attachments/assets/90883645-8393-418a-afb7-4e7982f100d8)

![Auto Scaling group monitoring with group metrics enabled](https://github.com/user-attachments/assets/2003ee67-999e-4d00-9db3-912967b6ad6a)

2. Choose **Target Groups**, then `LabTargetGroup`, then the **Targets** tab.
3. Wait until the registered instance shows **Healthy**. If it stays **Unhealthy**, the health check path is the first thing to check.

![Target group showing a healthy registered instance](https://github.com/user-attachments/assets/47e89ee2-3e18-4374-bd19-cf4d19156589)

4. Choose **Load Balancers**, select `ALB`, and copy the **DNS name**.
5. Open the DNS name in a browser.
6. Confirm the application loads and displays the instance metadata of whichever instance served the request.

![Application served through the Application Load Balancer](https://github.com/user-attachments/assets/150f98d2-2da1-4653-81a9-bf302dc18679)

### 13.8.9 Step 9: Test Scaling Out

The target tracking policy created two alarms automatically:

- **AlarmHigh** triggers scale-out when average CPU rises above the upper bound.
- **AlarmLow** triggers scale-in when average CPU falls below the lower bound.

1. Open **CloudWatch**, then **Alarms**, and confirm both exist in the `OK` state.

![CloudWatch alarms created by the target tracking policy](https://github.com/user-attachments/assets/5b5e3ae0-cd1e-463c-bbd5-dda0713655db)

2. Open the ALB DNS name in a browser.
3. Choose **Start artificial load**. Background worker processes begin driving CPU on whichever instance received the request.

![Application page with artificial load generation running](https://github.com/user-attachments/assets/eac1fef1-b3f7-4c5a-ab6a-44b0bae531c0)

   As an alternative, or in addition, connect to an instance over SSH and use `stress-ng`.
   ```bash
   sudo dnf install -y stress-ng
   stress-ng --cpu 0 --timeout 10m
   ```

4. Watch **CloudWatch**, then **Alarms**, and confirm **AlarmHigh** moves from `OK` to `In alarm`.

![AlarmHigh in the In alarm state](https://github.com/user-attachments/assets/d0457bc0-81f4-4959-b06b-ccebd3cf0536)

5. Watch **EC2**, then **Instances**, and confirm new `LoadBalancedAppServer` instances appear as the group scales out.

![New instances launched by the Auto Scaling group](https://github.com/user-attachments/assets/98258414-248b-4f7c-a553-96fe87a841e4)

6. Watch **Target Groups**, then `LabTargetGroup`, then **Targets**, and confirm new instances register and become **Healthy**.

![Target group with multiple healthy instances registered](https://github.com/user-attachments/assets/cb7ad36e-2299-4463-9b55-3edb007ff03e)

7. Refresh the browser page several times and confirm the displayed instance ID changes as the load balancer distributes requests.

![Application showing a different instance ID after refresh](https://github.com/user-attachments/assets/2e10eb0b-71bc-4801-b4fe-83930de5e6de)

![Auto Scaling activity history recording the scale-out events](https://github.com/user-attachments/assets/5e69f1f9-ea3d-4dd5-a862-c26b70317c60)

8. Check your email for the SNS launch notifications.

### 13.8.10 Step 10: Test Automatic Recovery

1. In **EC2**, choose **Instances** and select one of the `LoadBalancedAppServer` instances.
2. Choose **Instance state**, then **Terminate instance**, and confirm.
3. Wait one to three minutes.
4. Confirm the Auto Scaling group detects the drop below desired capacity and launches a replacement.

![Auto Scaling group replacing the terminated instance](https://github.com/user-attachments/assets/6b899ef4-62ac-4071-8a37-9ed54f62401c)

![Activity history showing the termination and the replacement launch](https://github.com/user-attachments/assets/3b29832e-c3d7-4f60-82d9-15adcdc51795)

5. Check your email for the terminate and launch notifications.

This is the property worth taking away. Nobody was paged, and nothing was repaired. The group simply noticed it was one instance short and fixed it.

### 13.8.11 Step 11: Stop the Load Test

1. In the browser, choose **Stop artificial load**.
2. If you used `stress-ng`, stop it on each instance.
   ```bash
   sudo pkill stress-ng
   ```
3. Watch the CPU metric fall, then **AlarmLow** enter the alarm state.
4. Confirm the group scales back in toward the desired capacity of 1. Scale-in is deliberately slower than scale-out, so allow several minutes.

### 13.8.12 Cleanup

Order matters, because each resource holds a dependency on the next.

1. Open **EC2**, choose **Auto Scaling Groups**, select `ALB-Scaling-Group`, and choose **Delete**. This terminates every instance it manages. Wait for it to finish.
2. Choose **Load Balancers**, select `ALB`, choose **Actions**, then **Delete load balancer**.
3. Choose **Target Groups**, select `LabTargetGroup`, choose **Actions**, then **Delete**.
4. Choose **Launch Templates**, select `ALBConfig`, choose **Actions**, then **Delete template**.
5. Choose **Instances**, select `MyAppServer`, choose **Instance state**, then **Terminate instance**.
6. Choose **AMIs**, select `AppServerAMI`, choose **Actions**, then **Deregister AMI**.
7. Choose **Snapshots** and delete the snapshot the AMI left behind. Deregistering an image does not remove its snapshot, and the snapshot keeps billing.
8. Choose **Security Groups**, delete `EC2-SG` first, then `ALB-SG`. `EC2-SG` references `ALB-SG`, so the order cannot be reversed.
9. Open **VPC**, choose **Your VPCs**, select `ASB-Lab-vpc`, choose **Actions**, then **Delete VPC**. This also removes the subnets, route tables, and internet gateway.
10. Open **SNS**, choose **Topics**, select `MyAppMonitor`, and delete it. Delete the subscription as well.
11. Open **CloudWatch**, choose **Alarms**, select the two alarms created by `ASBScalingPolicy`, and delete them.
12. Confirm no instances, load balancers, or NAT gateways remain in the Region.

---

## 13.9 End-of-Chapter Questions

**Q1.** A company needs to distribute HTTP traffic across multiple EC2 instances using URL path-based routing. Which load balancer should be used?

- A. Classic Load Balancer
- B. Network Load Balancer
- C. Application Load Balancer
- D. Gateway Load Balancer

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* The Application Load Balancer operates at layer 7 and can route on URL path, hostname, and HTTP headers; the Network Load Balancer sees only protocol and port.

**Q2.** Which service sends alerts when an Amazon CloudWatch alarm changes state?

- A. Amazon Simple Notification Service
- B. AWS CloudTrail
- C. AWS Trusted Advisor
- D. Amazon Route 53

**Answer: A.** *Target exam: AWS Certified Cloud Practitioner.* CloudWatch alarms publish to an SNS topic, which then delivers by email, SMS, HTTP endpoint, or Lambda.

**Q3.** A company wants EC2 capacity to increase automatically when CPU utilization exceeds 70%. Which combination achieves this?

- A. AWS CloudTrail and AWS Trusted Advisor
- B. Amazon CloudWatch and EC2 Auto Scaling
- C. Elastic Load Balancing and Amazon S3
- D. Amazon SNS and AWS Config

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* A CloudWatch alarm on the CPU metric triggers an EC2 Auto Scaling policy that adds instances.

**Q4.** An architect needs to mix On-Demand and Spot Instances in one Auto Scaling configuration, with versioning and the ability to roll back. What should be used?

- A. A launch configuration
- B. A launch template
- C. A CloudFormation stack
- D. EC2 Image Builder

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Launch templates support versioning and mixed instance policies; launch configurations are legacy and support neither.

**Q5.** An Auto Scaling group behind an Application Load Balancer keeps instances that pass EC2 status checks but return errors to users. What is the most likely cause?

- A. The health check grace period is too short
- B. ELB health checks are not enabled on the Auto Scaling group, so only instance-level status checks are used
- C. The load balancer is in a single Availability Zone
- D. Detailed monitoring is disabled

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* EC2 status checks confirm the instance is running, not that the application is responding; enabling ELB health checks lets the group replace instances the load balancer considers unhealthy.

**Q6.** A workload must sustain 99.99% availability. Approximately how much downtime does that permit per year?

- A. About 5 minutes
- B. About 52 minutes
- C. About 8.7 hours
- D. About 87.6 hours

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Four nines allows roughly 52 minutes annually; five nines allows about 5 minutes and three nines about 8.7 hours.
