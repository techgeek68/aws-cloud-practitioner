# Chapter 36: AWS SDKs

---

The CLI is for operating AWS. The SDKs are for building applications that call AWS. This chapter covers what is common to all of them first, then each language, then the concerns that apply regardless of language.

---

## 36.1 SDK Fundamentals

**Every SDK does the same three things:** resolve credentials, resolve a Region, and sign and send API requests, handling retries and pagination on the way.

**Credential resolution is identical to the CLI**, and follows the chain in section 31.3: explicit configuration, environment variables, the shared credentials and config files, container credentials, then instance metadata. This is why an application that works locally with a profile also works on an instance with a role, without a code change.

**Never construct a client with hardcoded credentials.** Every SDK allows it and no production code should. Let the default chain resolve them, and the same binary then runs in development, in CI, and on an instance.

**Region resolution** follows: explicit client configuration, then `AWS_REGION`, then the profile's region setting, then the instance's Region. A client constructed without a Region on a laptop with no default fails at the first call.

**Two client styles**

- **Low-level clients** map one to one onto the service API. Every service has one, and every operation is available.
- **Higher-level abstractions** exist for some services and languages, such as Boto3's resource interface or the S3 transfer managers. They are more convenient and cover less.

**Errors are typed.** Each SDK exposes service exceptions with an error code such as `AccessDenied`, `ThrottlingException`, or `ResourceNotFoundException`. Catch the specific error rather than a generic exception, because the correct response differs: retry a throttle, fail on an access denial, and treat a not-found as a legitimate outcome.

---

## 36.2 Python with Boto3

### 36.2.1 Install

```bash
# Debian or Ubuntu
sudo apt update && sudo apt install -y python3 python3-pip python3-venv

# RHEL, CentOS, Amazon Linux
sudo dnf install -y python3 python3-pip

# macOS
brew install python

# Windows PowerShell
winget install Python.Python.3.12
```

Verify:

```bash
python3 --version
pip3 --version
```

### 36.2.2 Virtual Environments

Always work in a virtual environment. Installing into the system Python breaks other tools eventually.

```bash
python3 -m venv .venv
source .venv/bin/activate          # Windows: .venv\Scripts\Activate.ps1

pip install boto3
pip freeze > requirements.txt

deactivate
```

### 36.2.3 Clients and Resources

```python
import boto3

# Low-level client: every API operation
ec2 = boto3.client("ec2", region_name="us-east-1")

# Resource interface: object-oriented, fewer services covered
s3 = boto3.resource("s3")

# A session, for a named profile or explicit configuration
session = boto3.Session(profile_name="prod", region_name="eu-west-1")
rds = session.client("rds")
```

Use a `Session` when the application must work against more than one profile, account, or Region in a single process. Otherwise the module-level client is fine.

### 36.2.4 Pagination

Never assume a list call returned everything. Most APIs cap a response and return a continuation token.

```python
import boto3

s3 = boto3.client("s3")
paginator = s3.get_paginator("list_objects_v2")

for page in paginator.paginate(Bucket="my-bucket", Prefix="logs/"):
    for obj in page.get("Contents", []):
        print(obj["Key"], obj["Size"])
```

Note `page.get("Contents", [])` rather than `page["Contents"]`. An empty page has no `Contents` key at all, and indexing it raises `KeyError`.

### 36.2.5 Waiters

```python
ec2 = boto3.client("ec2")

waiter = ec2.get_waiter("instance_running")
waiter.wait(
    InstanceIds=["i-0abc123"],
    WaiterConfig={"Delay": 15, "MaxAttempts": 40},
)
```

Waiters poll until a condition is met or attempts are exhausted. They replace hand-written sleep loops, which either poll too often or give up too early.

### 36.2.6 A Worked Example

```python
import boto3
from botocore.exceptions import ClientError

def running_instances(region: str) -> list[dict]:
    ec2 = boto3.client("ec2", region_name=region)
    paginator = ec2.get_paginator("describe_instances")
    results = []

    try:
        pages = paginator.paginate(
            Filters=[{"Name": "instance-state-name", "Values": ["running"]}]
        )
        for page in pages:
            for reservation in page["Reservations"]:
                for inst in reservation["Instances"]:
                    tags = {t["Key"]: t["Value"] for t in inst.get("Tags", [])}
                    results.append({
                        "id": inst["InstanceId"],
                        "type": inst["InstanceType"],
                        "az": inst["Placement"]["AvailabilityZone"],
                        "name": tags.get("Name", "-"),
                    })
    except ClientError as e:
        code = e.response["Error"]["Code"]
        if code == "UnauthorizedOperation":
            raise PermissionError(f"No permission to describe instances in {region}") from e
        raise

    return results


if __name__ == "__main__":
    for row in running_instances("us-east-1"):
        print(f"{row['id']:22} {row['type']:12} {row['az']:12} {row['name']}")
```

The filter is applied server side, so only running instances are transferred, per the same reasoning as section 32.5.

### 36.2.7 Uploading to S3

```python
import boto3
from boto3.s3.transfer import TransferConfig

s3 = boto3.client("s3")

config = TransferConfig(
    multipart_threshold=100 * 1024 * 1024,   # 100 MB
    max_concurrency=10,
    use_threads=True,
)

s3.upload_file(
    "large-file.zip", "my-bucket", "uploads/large-file.zip",
    Config=config,
    ExtraArgs={"ServerSideEncryption": "aws:kms", "SSEKMSKeyId": "alias/my-key"},
)
```

`upload_file` handles multipart upload, parallelism, and retries automatically, which is why it should be preferred over `put_object` for anything sizeable.

---

## 36.3 Node.js with AWS SDK v3

### 36.3.1 Setup

```bash
node --version    # 18 or later
npm init -y

npm install @aws-sdk/client-ec2 @aws-sdk/client-s3
```

**v3 is modular.** Each service is a separate package, so you install only what you use and the bundle stays small. This is the main difference from v2, which shipped every service in one package.

### 36.3.2 Clients and Commands

v3 uses a command pattern: construct a client, construct a command, send it.

```javascript
import { EC2Client, DescribeInstancesCommand } from "@aws-sdk/client-ec2";

const client = new EC2Client({ region: "us-east-1" });

const response = await client.send(
  new DescribeInstancesCommand({
    Filters: [{ Name: "instance-state-name", Values: ["running"] }],
  })
);

for (const reservation of response.Reservations ?? []) {
  for (const instance of reservation.Instances ?? []) {
    console.log(instance.InstanceId, instance.InstanceType);
  }
}
```

### 36.3.3 Pagination and Waiters

```javascript
import { S3Client, paginateListObjectsV2 } from "@aws-sdk/client-s3";

const client = new S3Client({ region: "us-east-1" });

for await (const page of paginateListObjectsV2(
  { client },
  { Bucket: "my-bucket", Prefix: "logs/" }
)) {
  for (const obj of page.Contents ?? []) {
    console.log(obj.Key, obj.Size);
  }
}
```

```javascript
import { EC2Client, waitUntilInstanceRunning } from "@aws-sdk/client-ec2";

const client = new EC2Client({ region: "us-east-1" });

await waitUntilInstanceRunning(
  { client, maxWaitTime: 300 },
  { InstanceIds: ["i-0abc123"] }
);
```

Paginators are async iterators, so `for await` consumes them naturally without token handling.

### 36.3.4 Middleware

v3 exposes the request pipeline, which is how you add a header, log timing, or inject a correlation ID across every call.

```javascript
client.middlewareStack.add(
  (next) => async (args) => {
    const start = Date.now();
    const result = await next(args);
    console.log(`call took ${Date.now() - start}ms`);
    return result;
  },
  { step: "build", name: "timingMiddleware" }
);
```

---

## 36.4 Java with SDK v2

### 36.4.1 Dependencies

Use the bill of materials so every module shares one version.

**Maven**

```xml
<dependencyManagement>
  <dependencies>
    <dependency>
      <groupId>software.amazon.awssdk</groupId>
      <artifactId>bom</artifactId>
      <version>2.28.0</version>
      <type>pom</type>
      <scope>import</scope>
    </dependency>
  </dependencies>
</dependencyManagement>

<dependencies>
  <dependency>
    <groupId>software.amazon.awssdk</groupId>
    <artifactId>ec2</artifactId>
  </dependency>
</dependencies>
```

**Gradle, Kotlin DSL**

```kotlin
dependencies {
    implementation(platform("software.amazon.awssdk:bom:2.28.0"))
    implementation("software.amazon.awssdk:ec2")
}
```

Import the specific service modules rather than a bundle, for the same reason as Node.js v3.

### 36.4.2 Clients

```java
import software.amazon.awssdk.regions.Region;
import software.amazon.awssdk.services.ec2.Ec2Client;
import software.amazon.awssdk.services.ec2.model.DescribeRegionsResponse;

public class ListRegions {
    public static void main(String[] args) {
        try (Ec2Client ec2 = Ec2Client.builder()
                .region(Region.US_EAST_1)
                .build()) {

            DescribeRegionsResponse response = ec2.describeRegions();
            response.regions().forEach(r ->
                System.out.println(r.regionName() + " " + r.endpoint()));
        }
    }
}
```

Clients are thread-safe and expensive to construct. Create one per service and reuse it for the life of the application. Creating a client per request is a common and costly mistake. The try-with-resources block above is appropriate for a short program; a long-running service holds the client as a singleton.

### 36.4.3 Pagination and Waiters

```java
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.model.ListObjectsV2Request;
import software.amazon.awssdk.services.s3.paginators.ListObjectsV2Iterable;

ListObjectsV2Request request = ListObjectsV2Request.builder()
        .bucket("my-bucket")
        .prefix("logs/")
        .build();

ListObjectsV2Iterable pages = s3.listObjectsV2Paginator(request);
pages.contents().forEach(obj ->
        System.out.println(obj.key() + " " + obj.size()));
```

```java
import software.amazon.awssdk.services.ec2.waiters.Ec2Waiter;

try (Ec2Waiter waiter = Ec2Waiter.builder().client(ec2).build()) {
    waiter.waitUntilInstanceRunning(r -> r.instanceIds("i-0abc123"));
}
```

The `.contents()` method flattens pages into one stream, so no page-boundary handling is needed.

---

## 36.5 .NET SDK

### 36.5.1 Setup

```bash
dotnet new console -n AwsDemo
cd AwsDemo

dotnet add package AWSSDK.EC2
dotnet add package AWSSDK.S3
```

### 36.5.2 Async Patterns

Every SDK operation is asynchronous. Use `async` and `await` throughout and never block on the result.

```csharp
using Amazon;
using Amazon.EC2;
using Amazon.EC2.Model;

var client = new AmazonEC2Client(RegionEndpoint.USEast1);

var response = await client.DescribeRegionsAsync(new DescribeRegionsRequest());

foreach (var region in response.Regions)
{
    Console.WriteLine($"{region.RegionName} {region.Endpoint}");
}
```

**Never call `.Result` or `.Wait()` on an SDK task.** In ASP.NET this deadlocks, and everywhere else it wastes a thread. Make the calling method `async` instead.

### 36.5.3 Pagination

```csharp
using Amazon.S3;
using Amazon.S3.Model;

var s3 = new AmazonS3Client(RegionEndpoint.USEast1);
var request = new ListObjectsV2Request { BucketName = "my-bucket", Prefix = "logs/" };

ListObjectsV2Response response;
do
{
    response = await s3.ListObjectsV2Async(request);
    foreach (var obj in response.S3Objects)
    {
        Console.WriteLine($"{obj.Key} {obj.Size}");
    }
    request.ContinuationToken = response.NextContinuationToken;
}
while (response.IsTruncated);
```

Newer service clients also expose `Paginators`, which removes the manual token loop:

```csharp
await foreach (var obj in s3.Paginators.ListObjectsV2(request).S3Objects)
{
    Console.WriteLine(obj.Key);
}
```

**Client lifetime.** Register the client as a singleton in dependency injection. Creating one per request exhausts sockets under load, for the same reason as `HttpClient`.

---

## 36.6 Cross-Cutting Concerns

### 36.6.1 Assuming a Role in Code

```python
import boto3

sts = boto3.client("sts")
assumed = sts.assume_role(
    RoleArn="arn:aws:iam::111122223333:role/CrossAccountRole",
    RoleSessionName="reporting-job",
)
creds = assumed["Credentials"]

target = boto3.client(
    "s3",
    aws_access_key_id=creds["AccessKeyId"],
    aws_secret_access_key=creds["SecretAccessKey"],
    aws_session_token=creds["SessionToken"],
)
```

Better still, define the role in `~/.aws/config` with `role_arn` and `source_profile`, as in section 32.4.2, and let the SDK assume and refresh it. Credentials from `assume_role` expire, and code holding them must handle renewal; a profile-based assumption refreshes automatically.

### 36.6.2 Retries and Backoff

Every SDK retries transient failures automatically. Understand the mode you are using.

| Mode | Behavior |
| --- | --- |
| `legacy` | The original behavior, fewest retries |
| `standard` | Consistent across SDKs, retries throttling and transient errors |
| `adaptive` | Adds client-side rate limiting that backs off when throttled |

```python
from botocore.config import Config

config = Config(
    retries={"max_attempts": 5, "mode": "standard"},
    connect_timeout=5,
    read_timeout=30,
)
client = boto3.client("dynamodb", config=config)
```

```bash
export AWS_RETRY_MODE=standard
export AWS_MAX_ATTEMPTS=5
```

**Adaptive mode is not a universal improvement.** It throttles your own client to protect the service, which is right for a bulk job and wrong for a latency-sensitive request path.

**Do not write your own retry loop around an SDK call.** You will end up retrying on top of the SDK's retries, multiplying the load during exactly the incident that caused the throttling.

### 36.6.3 Timeouts

Defaults are generous, which means a hung connection blocks far longer than a caller expects.

- **Connect timeout**: how long to wait to establish a connection. A few seconds is usually right.
- **Read timeout**: how long to wait for a response. Set it below the caller's own deadline, so the SDK fails before the caller does.

In Lambda specifically, set the read timeout below the function timeout. Otherwise the function is killed mid-call and the log shows a timeout with no indication of which call hung.

### 36.6.4 Logging

```python
import logging

logging.basicConfig(level=logging.INFO)
logging.getLogger("botocore").setLevel(logging.WARNING)   # quiet by default
logging.getLogger("botocore").setLevel(logging.DEBUG)     # full request detail
```

Debug logging includes the signed request and headers. It does not include the secret key, but it does include enough detail that it should not be enabled in production or written where it might be shipped to a log aggregator.

Enable **X-Ray** for distributed tracing where a request crosses services, as covered in section 23.7.

### 36.6.5 Testing

- **Unit tests should not call AWS.** Mock the client. In Python, `botocore.stub.Stubber` validates that your parameters are actually valid for the API, which a plain mock does not.
- **Integration tests should call AWS**, in an isolated account, against real resources created and destroyed by the test.
- **LocalStack** emulates many services locally and is useful for fast feedback, with the caveat that its behavior is not identical to AWS and passing locally is not proof.

```python
from botocore.stub import Stubber

client = boto3.client("s3", region_name="us-east-1")
stubber = Stubber(client)
stubber.add_response(
    "list_buckets",
    {"Buckets": [{"Name": "test-bucket"}]},
    {},
)
stubber.activate()
```

### 36.6.6 Common Errors

| Error | Meaning and response |
| --- | --- |
| `NoCredentialsError` or `Unable to locate credentials` | The chain resolved nothing. Check profile, environment, and instance role |
| `ExpiredToken` | Temporary credentials expired. Refresh, or use a role-based profile that refreshes automatically |
| `AccessDenied` | The identity lacks the permission. Read the ARN in the message and simulate the policy per section 33.7.6 |
| `ThrottlingException`, `TooManyRequestsException` | Rate limited. Let the SDK's retry handle it, or reduce concurrency |
| `ResourceNotFoundException` | Frequently the wrong Region rather than a missing resource |
| `ValidationException` | Malformed parameters. Read which field is named |
| `EndpointConnectionError` | No network path, or a service unavailable in that Region |
| `ProvisionedThroughputExceededException` | DynamoDB capacity exceeded, often a hot partition per section 20.4 |

### 36.6.7 Security Checklist

- No credentials in source code, ever. Use the default chain.
- No credentials in a repository, including in a `.env` file. Add it to `.gitignore`.
- Use IAM roles for anything running on AWS.
- Scope permissions to the operations the code actually performs, verified with Access Analyzer policy generation.
- Fetch secrets from Secrets Manager or Parameter Store at startup and cache them, per section 26.5.
- Pin SDK versions in the dependency manifest and update deliberately.
- Do not log request or response bodies that may contain personal or sensitive data.
- Enforce TLS, which every SDK does by default; do not disable certificate verification to work around a proxy.

---

## 36.7 End-of-Chapter Questions

**Q1.** A Python script lists S3 objects with `list_objects_v2` and processes the results, but consistently misses objects in a large bucket. What is wrong?

- A. The bucket needs versioning enabled
- B. The response is paginated and the script reads only the first page
- C. The prefix filter is incorrect
- D. The client lacks `s3:GetObject`

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* List operations cap the response and return a continuation token; a paginator handles this and a single call does not.

**Q2.** An application constructs a new AWS SDK client for every incoming request and begins failing under load with socket exhaustion. What is the fix?

- A. Increase the retry count
- B. Create the client once and reuse it, since clients are thread-safe and expensive to construct
- C. Switch to the CLI
- D. Enable adaptive retry mode

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* SDK clients hold connection pools and are designed to be long-lived singletons.

**Q3.** A developer wraps every SDK call in a custom retry loop with three attempts. What is the consequence during a throttling event?

- A. Requests succeed faster
- B. Retries multiply, since the SDK already retries internally, increasing load during the incident
- C. The SDK disables its own retries automatically
- D. Requests are queued locally

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Layering a manual loop on top of the SDK's retries multiplies the attempts and worsens the condition causing them.

**Q4.** A .NET application calls `.Result` on an SDK task inside an ASP.NET controller and intermittently hangs. What is the correct approach?

- A. Increase the read timeout
- B. Make the calling method `async` and `await` the task
- C. Use a synchronous SDK method
- D. Create a new client per call

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Blocking on an async task can deadlock the request context; the whole call chain should be asynchronous.

**Q5.** An application running on EC2 works in development using a local profile but fails in production with a credentials error. What is the most likely cause?

- A. The SDK version differs
- B. The instance has no instance profile attached, so the credential chain resolves nothing
- C. The Region is unset
- D. The application needs an access key in source code

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* The chain falls through to instance metadata on EC2, which returns nothing without an attached instance profile.

**Q6.** Which retry mode adds client-side rate limiting that slows the caller when throttling is detected?

- A. `legacy`
- B. `standard`
- C. `adaptive`
- D. `aggressive`

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* Adaptive mode suits bulk workloads; it is a poor choice on a latency-sensitive request path because it deliberately slows the client.
