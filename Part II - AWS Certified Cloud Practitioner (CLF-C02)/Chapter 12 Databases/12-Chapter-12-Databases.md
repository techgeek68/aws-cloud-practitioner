# Chapter 12: Databases

---

## 12.1 Relational vs Non-Relational Data

| Aspect | Relational (SQL) | Non-relational (NoSQL) |
| --- | --- | --- |
| Data storage | Rows and columns in tables | Key-value, document, graph, column family, time series |
| Schema | Fixed and defined before data is written | Flexible, defined per item |
| Querying | SQL, including joins across tables | Service APIs, usually against a single collection |
| Scaling | Primarily vertical, to a larger instance | Primarily horizontal, across partitions |
| Strength | Complex queries, joins, and multi-row transactions | Predictable low latency at very large scale |

The choice is driven by access pattern rather than data volume. If the application needs joins, ad hoc queries, and transactional integrity across several tables, use relational. If it looks up known items by key at high volume and low latency, use non-relational.

---

## 12.2 Amazon RDS

A managed relational database service. AWS handles provisioning, patching, backups, and failover, leaving schema design, query tuning, and access control to you.

### 12.2.1 Supported Engines

RDS supports seven engines:

- IBM Db2
- MariaDB
- Microsoft SQL Server
- MySQL
- Oracle Database
- PostgreSQL
- Amazon Aurora

Aurora is architecturally a separate service but is created and managed through the RDS console, which is why it appears in this list.

### 12.2.2 Features

- **DB instance classes** set the CPU, memory, and network capacity, using the same family naming as EC2.
- **Storage types** are General Purpose SSD (gp2 and gp3) and Provisioned IOPS SSD (io1 and io2). Magnetic storage exists only for backward compatibility and should not be used for new work.
- **Multi-AZ** synchronously replicates to a standby in another Availability Zone and fails over automatically.
- **Read replicas** use asynchronous replication to serve read traffic, and can be created in the same Region or in another one.
- **VPC integration** places instances in a DB subnet group with security groups controlling access.
- **Automated backups** provide point-in-time recovery within the retention window, and manual snapshots persist until deleted.

### 12.2.3 Multi-AZ and Read Replicas Are Not the Same Thing

This is the single most tested distinction in the chapter.

| | Multi-AZ | Read replica |
| --- | --- | --- |
| Purpose | High availability | Read scaling |
| Replication | Synchronous | Asynchronous |
| Serves traffic | No, the standby is idle until failover | Yes, accepts read queries |
| Failover | Automatic, via a DNS endpoint change | Manual promotion |
| Cross-Region | Multi-AZ is within one Region | Replicas can be cross-Region |

A read replica does not provide automatic failover. A Multi-AZ standby does not reduce load on the primary. Designs that need both use both.

### 12.2.4 Pricing

- Billed per clock hour that the instance runs.
- On-Demand or Reserved Instances, with one-year and three-year terms.
- Storage billed per provisioned GB-month, plus I/O charges on the storage types that meter them.
- Inbound data transfer is free; outbound is tiered.
- Multi-AZ roughly doubles the instance cost, because a second instance is running.

### 12.2.5 When RDS Fits

Good for complex queries, joins, and transactions at medium to high query rates with strong durability requirements: web and mobile applications, e-commerce platforms, and online games.

Poor for write throughput at internet scale, workloads requiring manual sharding, and simple key-value lookups. Those belong in DynamoDB.

---

## 12.3 Amazon Aurora

A cloud-native relational engine built by AWS, compatible with MySQL and PostgreSQL so that most applications migrate with little or no code change.

- AWS states Aurora delivers up to **five times the throughput of standard MySQL** and up to **three times the throughput of standard PostgreSQL** on the same hardware. Both figures appear in exam questions.
- **Storage** is a distributed cluster volume that grows automatically in 10 GiB increments, replicated **six ways across three Availability Zones**. It scales to 128 TiB, and to 256 TiB on newer configurations.
- **Availability** is designed for up to 99.99% in a single Region and up to 99.999% across Regions.
- **Aurora Serverless v2** scales capacity up and down with demand, which suits variable or unpredictable workloads.
- **Aurora Global Database** replicates to secondary Regions with typical replication lag under a second.
- **Security** covers VPC isolation, encryption at rest with KMS and in transit with TLS, IAM database authentication, and compliance with HIPAA, FedRAMP, and other regimes.
- **Zero-ETL integration** moves data into Amazon Redshift for near real-time analytics without a pipeline to build.

AWS positions Aurora as commercial-grade performance at open-source economics, at roughly a tenth of the cost of comparable commercial databases.

---

## 12.4 Amazon DynamoDB

A serverless key-value and document database delivering consistent single-digit millisecond performance at any scale.

- **Data model.** Tables contain items, and items contain attributes. Only the key attributes must exist on every item.
- **Primary key.** Every table needs a **partition key**. An optional **sort key** makes the key composite and enables range queries within a partition.
- **Storage and replication.** Data is stored on SSDs and replicated synchronously across three Availability Zones in the Region. AWS manages partitioning entirely.
- **Capacity modes.** On-demand scales automatically and bills per request. Provisioned reserves read and write capacity units and is cheaper for steady predictable traffic.
- **Secondary indexes.** A global secondary index allows queries on a different partition key. A local secondary index allows a different sort key on the same partition key.
- **DynamoDB Streams** emit an ordered record of item changes, commonly consumed by Lambda.
- **Global tables** add multi-active replication across Regions, where every replica accepts reads and writes. Cross-Region replication is opt-in; the default is multi-AZ within one Region.

Suits IoT, mobile and web applications, gaming, and ad technology. Key design and access patterns are covered in section 20.4.

---

## 12.5 Amazon Redshift

A managed petabyte-scale data warehouse for analytics and business intelligence.

- A **leader node** plans and coordinates queries; **compute nodes** execute them in parallel across distributed data.
- **Columnar storage** reads only the columns a query needs, which cuts I/O dramatically for analytical queries.
- **Massively parallel processing** spreads execution across nodes.
- **Redshift Serverless** and Elastic Resize adjust capacity without downtime.
- **Encryption** is available at rest and in transit.
- **Zero-ETL integrations** bring data from Aurora, RDS, and DynamoDB in near real time.

Redshift is built for OLAP, meaning analytical queries scanning large volumes. It is not a transactional database, and using it as one produces poor results.

---

## 12.6 Purpose-Built Database Services

| Service | Model | Typical use |
| --- | --- | --- |
| Amazon ElastiCache | In-memory cache, Redis or Memcached | Reducing repeated reads against a database, session storage |
| Amazon MemoryDB | In-memory, durable, Redis compatible | A primary database needing microsecond reads and durability |
| Amazon Neptune | Graph | Fraud detection, recommendations, social networks, knowledge graphs |
| Amazon DocumentDB | Document, MongoDB compatible | Content management, catalogs, applications already written for MongoDB |
| Amazon Timestream | Time series | IoT telemetry, application and infrastructure metrics |
| Amazon Keyspaces | Wide column, Cassandra compatible | Existing Cassandra workloads moving to a managed service |

---

## 12.7 Choosing the Right Database

| Requirement | Service |
| --- | --- |
| Managed relational database on a standard engine | Amazon RDS |
| High performance relational, MySQL or PostgreSQL compatible | Amazon Aurora |
| Fast, flexible key-value or document store | Amazon DynamoDB |
| An engine or operating system configuration RDS does not support | A database on EC2, self-managed |
| Petabyte-scale analytics and reporting | Amazon Redshift |
| Sub-millisecond reads of frequently requested data | Amazon ElastiCache |
| Relationships are the query, not an afterthought | Amazon Neptune |
| Time-stamped measurements at high ingest rates | Amazon Timestream |

Work through it in this order: is the data relational; does the access pattern need joins and transactions; what latency does the application require; and how large will it get. Answer those four and the service usually selects itself.

---

## 12.8 Database Case Studies

**Case 1: Data protection and management.** A product needs a relational store for configuration, a flexible store for metadata, object storage, and long-term archive. The answer is RDS or Aurora for configuration, DynamoDB for metadata whose shape varies, Amazon S3 for objects, and S3 Glacier Deep Archive for retention.

**Case 2: Shipping company migration.** A legacy on-premises Oracle estate must move to cloud-native services and handle both structured and semi-structured data with less operational overhead. The answer is Aurora PostgreSQL to replace Oracle, using AWS DMS and the Schema Conversion Tool for the migration, with DynamoDB for semi-structured operational data.

**Case 3: Online payment processing.** Flash sales drive millions of transactions daily, read volume spikes unpredictably, and the workload is subject to PCI DSS. The answer is RDS with read replicas to absorb read traffic, IAM for access control, and KMS for encryption. Multi-AZ handles availability; the replicas handle scale.

---

## 12.9 Lab: Build a Database Server and Interact with It

This lab builds the network, launches a Multi-AZ RDS MySQL instance in private subnets, launches an EC2 web server in a public subnet, and connects the two through a PHP application.

**Cost warning.** This lab uses a Multi-AZ `db.t3.micro` instance, which runs two instances and is not covered by the free tier. Complete it in one sitting and follow the cleanup.

**Application files.** The four PHP files are provided alongside this chapter in `lab-files/12-rds-php-app/`.

### 12.9.1 Step 1: Build the Network

**Create the VPC**

1. Open the **VPC** console.
2. Confirm the Region selector shows **US East (N. Virginia)**.
3. Choose **Your VPCs**, then **Create VPC**.
4. Under **Resources to create**, select **VPC only**.
5. Set **Name tag** to `Lab VPC`.
6. Set **IPv4 CIDR block** to `10.0.0.0/16`.
7. Choose **Create VPC**.

**Create four subnets**

8. Choose **Subnets**, then **Create subnet**.
9. Set **VPC ID** to `Lab VPC`.
10. Create `Public-subnet-01` in `us-east-1a` with CIDR `10.0.0.0/24`.
11. Choose **Add new subnet** and create `Private-subnet-01` in `us-east-1a` with CIDR `10.0.1.0/24`.
12. Choose **Add new subnet** and create `Private-subnet-02` in `us-east-1b` with CIDR `10.0.2.0/24`.
13. Choose **Create subnet**.

    Two private subnets in two Availability Zones are required. A DB subnet group must span at least two zones, and Multi-AZ needs somewhere to place the standby.

14. Select `Public-subnet-01`.
15. Choose **Actions**, then **Edit subnet settings**.
16. Select **Enable auto-assign public IPv4 address** and choose **Save**.

**Create and attach the internet gateway**

17. Choose **Internet gateways**, then **Create internet gateway**.
18. Set **Name tag** to `Lab-igw`.
19. Choose **Create internet gateway**.
20. Choose **Actions**, then **Attach to VPC**.
21. Select `Lab VPC` and choose **Attach internet gateway**.

**Create the public route table**

22. Choose **Route tables**, then **Create route table**.
23. Set **Name** to `Lab-public-rt` and **VPC** to `Lab VPC`.
24. Choose **Create route table**.
25. Open the **Routes** tab and choose **Edit routes**.
26. Choose **Add route**, set **Destination** to `0.0.0.0/0` and **Target** to **Internet Gateway**, then select `Lab-igw`.
27. Choose **Save changes**.
28. Open the **Subnet associations** tab and choose **Edit subnet associations**.
29. Select `Public-subnet-01` and choose **Save associations**.

**Create the security groups**

30. Choose **Security groups**, then **Create security group**.
31. Set **Security group name** to `Web Security Group`.
32. Set **Description** to `Allow HTTP and SSH to the web server`.
33. Set **VPC** to `Lab VPC`.
34. Add an inbound rule: **Type** `HTTP`, **Source** **Anywhere-IPv4**.
35. Add an inbound rule: **Type** `SSH`, **Source** **My IP**.
36. Leave outbound rules at the default and choose **Create security group**.
37. Choose **Create security group** again.
38. Set **Security group name** to `DB Security Group`.
39. Set **Description** to `Allow MySQL from the web server only`.
40. Set **VPC** to `Lab VPC`.
41. Add an inbound rule: **Type** `MYSQL/Aurora`, port 3306, and set **Source** to the `Web Security Group`.
42. Leave outbound rules at the default and choose **Create security group**.

    Referencing the web server's security group rather than a CIDR range means only that tier can reach the database, whatever addresses its instances happen to have.

### 12.9.2 Step 2: Launch the RDS Instance

**Create the DB subnet group**

1. Open the **Aurora and RDS** console.
2. Choose **Subnet groups**, then **Create DB subnet group**.
3. Set **Name** to `lab-db-subnet-group`.
4. Set **Description** to `Private subnets for the lab database`.
5. Set **VPC** to `Lab VPC`.
6. Under **Add subnets**, select Availability Zones `us-east-1a` and `us-east-1b`.
7. Select `Private-subnet-01` and `Private-subnet-02`.
8. Choose **Create**.

**Create the database**

9. Choose **Databases**, then **Create database**.
10. Select **Standard create**, which exposes the full configuration.
11. Under **Engine options**, set **Engine type** to **MySQL** and **Engine version** to the latest MySQL 8.0 release available.
12. Under **Templates**, select **Dev/Test**. The Free tier template does not offer Multi-AZ, which this lab uses.
13. Under **Availability and durability**, select **Multi-AZ DB instance**.
14. Set **DB instance identifier** to `lab-db`.
15. Set **Master username** to `admin`.
16. Set **Credentials management** to **Self managed**.
17. Set **Master password** and **Confirm password** to `lab-password`.
18. Under **Instance configuration**, choose **Burstable classes** and select `db.t3.micro`.
19. Under **Storage**, set **Storage type** to **General Purpose SSD (gp3)**.
20. Set **Allocated storage** to `20` GiB.
21. Clear **Enable storage autoscaling**.
22. Under **Connectivity**, select **Don't connect to an EC2 compute resource**.
23. Set **VPC** to `Lab VPC`.
24. Set **DB subnet group** to `lab-db-subnet-group`.
25. Set **Public access** to **No**.
26. Under **VPC security group**, remove the default group and select `DB Security Group`.
27. Under **Monitoring**, clear **Enable Enhanced monitoring**.
28. Expand **Additional configuration** and set **Initial database name** to `lab`. Without this, RDS creates an instance with no database inside it.
29. Leave **Automated backups** enabled with the default retention.
30. Choose **Create database**.
31. The status shows **Creating** for several minutes. Continue to step 3 while it provisions.
32. Once the status reads **Available**, open `lab-db`, choose **Connectivity & security**, and copy the **Endpoint**. You will need it in step 5.

### 12.9.3 Step 3: Launch the Web Server

1. Open the **EC2** console and choose **Launch instances**.
2. Set **Name** to `Web Server`.
3. Select the latest **Amazon Linux 2023** AMI.
4. Set **Instance type** to `t3.micro`.
5. Under **Key pair (login)**, choose **Create new key pair**, name it `dbkey`, set type **RSA** and format **.pem**, then choose **Create key pair**. Store the downloaded file somewhere you can find it.
6. Next to **Network settings**, choose **Edit**.
7. Set **VPC** to `Lab VPC`.
8. Set **Subnet** to `Public-subnet-01`.
9. Set **Auto-assign public IP** to **Enable**.
10. Under **Firewall**, choose **Select existing security group** and select `Web Security Group`.
11. Leave the remaining settings at their defaults and choose **Launch instance**.
12. Wait for **Running** with **3/3 checks passed**, then note the **Public IPv4 address**.

### 12.9.4 Step 4: Configure the Web Server

**Connect**

1. Set permissions on the key file.
   - Linux or macOS:
     ```bash
     chmod 400 dbkey.pem
     ```
   - Windows PowerShell:
     ```powershell
     icacls "dbkey.pem" /inheritance:r
     icacls "dbkey.pem" /grant:r "$($env:USERNAME):(R)"
     ```
2. Connect over SSH.
   ```bash
   ssh -i dbkey.pem ec2-user@<EC2-Public-IP>
   ```

   If `icacls` or `ssh` is not found on Windows, call them by full path from `C:\Windows\System32`, and install the SSH client from an elevated PowerShell with `Add-WindowsCapability -Online -Name OpenSSH.Client~~~~0.0.1.0`.

**Install the software**

3. Install Apache, PHP, and the PHP MySQL extension.
   ```bash
   sudo dnf install -y httpd php php-mysqlnd
   ```
4. Enable and start Apache.
   ```bash
   sudo systemctl enable httpd --now
   ```
5. Confirm it is running.
   ```bash
   sudo systemctl status httpd
   ```
6. Check the output shows `Active: active (running)`, then press `Q` to exit.

   Amazon Linux 2023 uses `dnf`. On the older Amazon Linux 2 AMI, substitute `yum`; the package names are the same.

**Deploy the application**

7. Create each of the four files in `/var/www/html/`, pasting the contents from `lab-files/12-rds-php-app/`.
   ```bash
   sudo vi /var/www/html/config.php
   sudo vi /var/www/html/api.php
   sudo vi /var/www/html/login.php
   sudo vi /var/www/html/index.php
   ```
   Use `nano` in place of `vi` if you prefer.
8. Set ownership so Apache can read the files.
   ```bash
   sudo chown -R apache:apache /var/www/html
   ```
9. Set permissions.
   ```bash
   sudo chmod -R 644 /var/www/html/*.php
   ```
10. Restart Apache.
    ```bash
    sudo systemctl restart httpd
    ```

### 12.9.5 Step 5: Connect and Test

1. Confirm the RDS instance shows **Available**.
2. Copy the endpoint from **RDS**, **Databases**, `lab-db`, **Connectivity & security**, **Endpoint**.
3. Open a browser and go to `http://<EC2-Public-IP>`.
4. On the login page, enter the endpoint as the host, `admin` as the user, `lab-password` as the password, and `lab` as the database name.
5. Choose **Connect**.
6. Confirm you are redirected to the main application page.

![PHP application login page prompting for database connection details](https://github.com/user-attachments/assets/116d9cfe-6988-4d5e-aa07-9e2d422d91e0)

![Application connected to the RDS database](https://github.com/user-attachments/assets/bcae8e31-d857-441d-a298-1514f8395e61)

![Query results returned from the RDS MySQL instance](https://github.com/user-attachments/assets/5bcbf75d-903c-448e-8f50-ccdff1558aee)

**If the connection fails**, work through these in order:

- Confirm the RDS status is **Available** rather than still creating.
- Confirm `DB Security Group` has an inbound rule for port 3306 sourced from `Web Security Group`.
- Confirm the endpoint was copied in full, without the port suffix.
- Confirm the initial database name `lab` was set in step 2. If it was not, the instance has no database to connect to.
- Check the Apache error log with `sudo tail -50 /var/log/httpd/error_log`.

### 12.9.6 Cleanup

Delete in this order, since each resource depends on the ones after it.

1. Open **RDS**, choose **Databases**, select `lab-db`, choose **Actions**, then **Delete**. Clear **Create final snapshot**, acknowledge the confirmation, and type the confirmation phrase.
2. Wait until the instance disappears from the list. The subnet group cannot be deleted while it is in use.
3. Open **EC2**, select `Web Server`, choose **Instance state**, then **Terminate instance**.
4. Return to **RDS**, choose **Subnet groups**, select `lab-db-subnet-group`, and delete it.
5. Open **EC2**, choose **Key pairs**, select `dbkey`, and delete it.
6. Open **VPC**, choose **Security groups**, delete `DB Security Group` first, then `Web Security Group`.
7. Choose **Internet gateways**, select `Lab-igw`, choose **Actions**, then **Detach from VPC**, then **Delete internet gateway**.
8. Choose **Subnets**, select all four lab subnets, and delete them.
9. Choose **Route tables**, select `Lab-public-rt`, and delete it. Do not delete the main route table.
10. Choose **Your VPCs**, select `Lab VPC`, and delete it.

---

## 12.10 Challenge Lab: Amazon RDS with a Full-Stack Student App

This deploys an Express backend and a React frontend against an RDS PostgreSQL instance, on an Ubuntu EC2 host. It assumes the network from section 12.9 or an equivalent VPC with a public subnet and two private subnets.

**Application files.** All seven files are provided alongside this chapter in `lab-files/12-rds-fullstack/`.

### 12.10.1 Step 1: Create the PostgreSQL Instance

1. Open the **Aurora and RDS** console.
2. Choose **Databases**, then **Create database**.
3. Select **Standard create**.
4. Set **Engine type** to **PostgreSQL** and choose a current version.
5. Under **Templates**, select **Dev/Test**.
6. Set **DB instance identifier** to `challenge-lab-db`.
7. Set **Master username** to `postgres`.
8. Set a strong master password and record it somewhere safe. Do not reuse the example value from the source notes.
9. Set **DB instance class** to `db.t3.micro`.
10. Set **Storage type** to **General Purpose SSD (gp3)** with `20` GiB allocated.
11. Set **VPC** to your lab VPC and select a DB subnet group spanning two Availability Zones.
12. Set **Public access** to **No**.
13. Select a security group that allows inbound TCP 5432 from the EC2 instance's security group.
14. Expand **Additional configuration** and set **Initial database name** to `MyTestDB`.
15. Choose **Create database**.
16. Once the status reads **Available**, copy the endpoint. It has the form `challenge-lab-db.c9kigacmenen.us-east-1.rds.amazonaws.com`.

### 12.10.2 Step 2: Launch and Prepare the EC2 Instance

1. Launch an EC2 instance running **Ubuntu**, in the public subnet, with a public IP.
2. Attach a security group allowing SSH on 22 from your address, HTTP on 80 from anywhere, TCP 3000 for the React development server, and TCP 4000 for the API.
3. Create or select a key pair named `myDBserverLogin`.
4. Connect over SSH. The Ubuntu default user is `ubuntu`, not `ec2-user`.
   ```bash
   ssh -i "myDBserverLogin.pem" ubuntu@<EC2-Public-IP>
   ```
5. Install Node.js 22 and build tools.
   ```bash
   curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash -
   sudo apt-get install -y nodejs git build-essential
   ```
6. Verify the versions.
   ```bash
   node -v
   npm -v
   ```
7. Install the PostgreSQL client.
   ```bash
   sudo apt-get install -y postgresql-client
   ```

### 12.10.3 Step 3: Verify Database Connectivity

1. Connect to the database, substituting your endpoint.
   ```bash
   psql "host=<Your-Database-Endpoint> user=postgres dbname=MyTestDB port=5432"
   ```
2. Enter the master password when prompted.
3. Confirm the server responds.
   ```sql
   SELECT version();
   ```
4. Exit.
   ```sql
   \q
   ```

   If this fails, the application will fail too. Fix connectivity here before continuing: check the security group rule for TCP 5432, confirm the instance and database are in the same VPC, and confirm the database status is **Available**.

### 12.10.4 Step 4: Create the Directory Structure

1. Create the directories and empty files.
   ```bash
   sudo mkdir -p /var/www/html/{backend,frontend/{public,src}} && \
   sudo touch /var/www/html/backend/{db.js,package.json,server.js} \
     /var/www/html/frontend/{package.json,public/index.html,src/{App.js,index.js}}
   ```
2. Install `tree` if it is not present, then confirm the layout.
   ```bash
   sudo apt-get install -y tree
   tree /var/www/html/
   ```

![Directory tree showing the backend and frontend structure](https://github.com/user-attachments/assets/8be0b23f-0f29-46fd-bcd0-e727f353c468)

### 12.10.5 Step 5: Deploy the Backend

1. Populate the three backend files from `lab-files/12-rds-fullstack/`, mapping them as follows.

   | Source file | Destination |
   | --- | --- |
   | `backend-package.json` | `/var/www/html/backend/package.json` |
   | `backend-db.js` | `/var/www/html/backend/db.js` |
   | `backend-server.js` | `/var/www/html/backend/server.js` |

2. Create the environment file.
   ```bash
   sudo vi /var/www/html/backend/.env
   ```
3. Enter the following, substituting your own endpoint and the password you set in step 1.

   ```
   DB_HOST=<Your-Database-Endpoint>
   DB_USER=postgres
   DB_PASSWORD=<your-master-password>
   DB_NAME=MyTestDB
   DB_PORT=5432
   PORT=4000
   ```

   The source notes contained a real-looking password in this file. Never commit a `.env` file to source control, and never reuse a credential published in study material.

4. Restrict the file so only its owner can read it.
   ```bash
   sudo chmod 600 /var/www/html/backend/.env
   ```

![Environment file configured with the database connection values](https://github.com/user-attachments/assets/b67d5931-8595-49f4-89fb-bd1922cd25e4)

5. Set ownership on the application directory.
   ```bash
   sudo chown -R ubuntu:ubuntu /var/www/html
   ```
6. Install the backend dependencies.
   ```bash
   cd /var/www/html/backend
   npm install
   ```
7. Start the API.
   ```bash
   npm start
   ```
8. Confirm the output reports a successful connection to RDS PostgreSQL, that the `students` table is ready, and that the server is listening on port 4000. `server.js` creates the table on first run, so no manual schema step is needed.

![Backend server connected to RDS and listening on port 4000](https://github.com/user-attachments/assets/f14a56d4-9c51-4bbe-9381-a39b7caf5e80)

### 12.10.6 Step 6: Deploy the Frontend

1. Open a second SSH session, leaving the backend running in the first.
2. Populate the four frontend files from `lab-files/12-rds-fullstack/`.

   | Source file | Destination |
   | --- | --- |
   | `frontend-package.json` | `/var/www/html/frontend/package.json` |
   | `frontend-public-index.html` | `/var/www/html/frontend/public/index.html` |
   | `frontend-src-index.js` | `/var/www/html/frontend/src/index.js` |
   | `frontend-src-App.js` | `/var/www/html/frontend/src/App.js` |

3. Install the dependencies.
   ```bash
   cd /var/www/html/frontend
   npm install
   ```
4. Start the development server, pointing it at the API on the instance's public address.
   ```bash
   REACT_APP_API_URL=http://<EC2-Public-IP>:4000 npm start
   ```
5. Open `http://<EC2-Public-IP>:3000` in a browser.
6. Add a student using the form and confirm the record appears in the table below it.
7. Search for the student by first and last name.
8. Edit the record and confirm the change persists.
9. Delete the record and confirm it disappears.

![Student database web application running in the browser](https://github.com/user-attachments/assets/ba399494-9cf7-4c6e-bedb-eeb72633bd37)

![Student records stored in and retrieved from RDS PostgreSQL](https://github.com/user-attachments/assets/052202fe-ad34-429b-adfe-f9e296c8c1e0)

### 12.10.7 Notes on This Architecture

This is a lab arrangement, not a production one. Three things would change before deployment:

- The React development server is not a production web server. A real deployment runs `npm run build` and serves the static output from Nginx, Apache, or Amazon S3 with CloudFront.
- The API is exposed directly on port 4000. It belongs behind an Application Load Balancer with TLS.
- The database password is in a file on the instance. It belongs in AWS Secrets Manager, retrieved at startup through an instance role, as covered in section 19.7.

### 12.10.8 Cleanup

1. Stop both Node processes with `Ctrl+C` in each SSH session.
2. Open **RDS**, select `challenge-lab-db`, choose **Actions**, then **Delete**, and clear the final snapshot option.
3. Open **EC2**, select the Ubuntu instance, choose **Instance state**, then **Terminate instance**.
4. Delete the DB subnet group once the database has gone.
5. Delete the security groups, database group first.
6. Delete the key pair.
7. If you built the network solely for this lab, remove the internet gateway, subnets, route table, and VPC in that order.

---

## 12.11 End-of-Chapter Questions

**Q1.** A company needs a relational database that automatically scales storage, replicates six ways across three Availability Zones, and is compatible with MySQL. Which service should be used?

- A. Amazon RDS for MySQL
- B. Amazon DynamoDB
- C. Amazon Aurora
- D. Amazon Redshift

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* Aurora is the only service in the RDS family with a distributed storage layer that grows automatically and replicates six ways across three zones while remaining MySQL and PostgreSQL compatible.

**Q2.** What is the primary purpose of a Multi-AZ deployment in Amazon RDS?

- A. Improve read performance by distributing queries
- B. Reduce storage costs by using a secondary instance
- C. Provide high availability and automatic failover
- D. Enable replication across AWS Regions

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* The standby is synchronous and idle, existing only to take over on failure; it serves no read traffic.

**Q3.** An application on Amazon RDS for MySQL experiences unpredictable spikes in read traffic. Which feature addresses read scaling without changing the primary instance?

- A. Enable Multi-AZ
- B. Create read replicas
- C. Enable automated backups
- D. Purchase Reserved Instances

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* Read replicas asynchronously serve read queries, offloading the primary; Multi-AZ is a failover mechanism and adds no read capacity.

**Q4.** Which service best suits session data and user profiles requiring single-digit millisecond responses and horizontal scalability?

- A. Amazon RDS
- B. Amazon Redshift
- C. Amazon DynamoDB
- D. Amazon Aurora

**Answer: C.** *Target exam: AWS Certified Cloud Practitioner.* DynamoDB is built for high-volume key-based access at consistent low latency, which is exactly the session and profile pattern.

**Q5.** An analytics team runs complex aggregations across several years of sales data, scanning billions of rows but only a handful of columns per query. Which service fits?

- A. Amazon RDS for PostgreSQL
- B. Amazon DynamoDB
- C. Amazon Redshift
- D. Amazon ElastiCache

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Columnar storage means Redshift reads only the columns the query touches, and massively parallel processing spreads the scan across compute nodes.

**Q6.** A financial application needs an RDS database that survives the loss of an Availability Zone with no manual intervention, and separately needs to serve a growing volume of reporting queries without slowing transactions. What should the architect deploy?

- A. Multi-AZ only
- B. Read replicas only
- C. Multi-AZ for failover, plus one or more read replicas for the reporting workload
- D. A larger instance class

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* The two features solve different problems and are commonly deployed together; neither substitutes for the other.
