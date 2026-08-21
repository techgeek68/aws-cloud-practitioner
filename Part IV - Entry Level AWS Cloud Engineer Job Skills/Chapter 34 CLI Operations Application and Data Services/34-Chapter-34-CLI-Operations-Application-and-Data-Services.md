# Chapter 34: CLI Operations: Application and Data Services

---

Six services, each following the same shape: create, configure, operate, monitor, clean up. Conventions from section 31.4 apply, and every section assumes you have confirmed identity and Region as in section 33.

**Reference variables used throughout**

```bash
REGION=us-east-1
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
```

---

## 34.1 Amazon RDS

### 34.1.1 Discovery

```bash
aws rds describe-db-instances \
  --query 'DBInstances[].{ID:DBInstanceIdentifier,Engine:Engine,Class:DBInstanceClass,Status:DBInstanceStatus,MultiAZ:MultiAZ}' \
  --output table

aws rds describe-db-instances --db-instance-identifier <DB_IDENTIFIER>

aws rds describe-db-engine-versions --engine postgres \
  --query 'DBEngineVersions[].EngineVersion' --output text

aws rds describe-orderable-db-instance-options \
  --engine mysql --engine-version <ENGINE_VERSION> \
  --query 'OrderableDBInstanceOptions[].DBInstanceClass' --output text | tr '\t' '\n' | sort -u
```

The last command answers "which instance classes can I actually use for this engine version in this Region," which is worth checking before a create call fails.

### 34.1.2 Create

```bash
aws rds create-db-subnet-group \
  --db-subnet-group-name <SUBNET_GROUP> \
  --db-subnet-group-description "Private subnets" \
  --subnet-ids <SUBNET_ID_A> <SUBNET_ID_B>

aws rds create-db-instance \
  --db-instance-identifier <DB_IDENTIFIER> \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --master-username admin \
  --manage-master-user-password \
  --allocated-storage 20 \
  --storage-type gp3 \
  --storage-encrypted \
  --db-subnet-group-name <SUBNET_GROUP> \
  --vpc-security-group-ids <SG_ID> \
  --no-publicly-accessible \
  --multi-az \
  --backup-retention-period 7 \
  --tags Key=Environment,Value=dev
```

`--manage-master-user-password` has AWS generate the password and store it in Secrets Manager with rotation, which is better than passing `--master-user-password` on a command line that lands in shell history. Retrieve it with:

```bash
SECRET_ARN=$(aws rds describe-db-instances --db-instance-identifier <DB_IDENTIFIER> \
  --query 'DBInstances[0].MasterUserSecret.SecretArn' --output text)
aws secretsmanager get-secret-value --secret-id "$SECRET_ARN" --query SecretString --output text
```

### 34.1.3 Wait and Connect

```bash
aws rds wait db-instance-available --db-instance-identifier <DB_IDENTIFIER>

ENDPOINT=$(aws rds describe-db-instances --db-instance-identifier <DB_IDENTIFIER> \
  --query 'DBInstances[0].Endpoint.Address' --output text)
PORT=$(aws rds describe-db-instances --db-instance-identifier <DB_IDENTIFIER> \
  --query 'DBInstances[0].Endpoint.Port' --output text)

echo "$ENDPOINT:$PORT"
```

### 34.1.4 Snapshots, Restore, and Copy

```bash
aws rds create-db-snapshot \
  --db-instance-identifier <DB_IDENTIFIER> --db-snapshot-identifier <SNAPSHOT_ID>
aws rds wait db-snapshot-available --db-snapshot-identifier <SNAPSHOT_ID>

aws rds describe-db-snapshots --db-instance-identifier <DB_IDENTIFIER> \
  --snapshot-type manual \
  --query 'DBSnapshots[].{ID:DBSnapshotIdentifier,Created:SnapshotCreateTime,Size:AllocatedStorage}' \
  --output table

aws rds restore-db-instance-from-db-snapshot \
  --db-instance-identifier <DB_IDENTIFIER>-restored \
  --db-snapshot-identifier <SNAPSHOT_ID> \
  --db-subnet-group-name <SUBNET_GROUP>

aws rds copy-db-snapshot \
  --source-db-snapshot-identifier arn:aws:rds:<REGION>:<ACCOUNT_ID>:snapshot:<SNAPSHOT_ID> \
  --target-db-snapshot-identifier <SNAPSHOT_ID>-dr \
  --kms-key-id <KMS_KEY_ID> --region <TARGET_REGION>

aws rds delete-db-snapshot --db-snapshot-identifier <SNAPSHOT_ID>
```

A restore always creates a **new** instance. It never rolls the existing one back, as covered in section 20.7.

**Point-in-time restore**

```bash
aws rds restore-db-instance-to-point-in-time \
  --source-db-instance-identifier <DB_IDENTIFIER> \
  --target-db-instance-identifier <DB_IDENTIFIER>-pitr \
  --restore-time 2026-07-22T10:30:00Z
```

### 34.1.5 Modify and Scale

```bash
aws rds modify-db-instance --db-instance-identifier <DB_IDENTIFIER> \
  --allocated-storage 50 --apply-immediately

aws rds modify-db-instance --db-instance-identifier <DB_IDENTIFIER> \
  --db-instance-class db.t3.small --apply-immediately

aws rds modify-db-instance --db-instance-identifier <DB_IDENTIFIER> \
  --multi-az --apply-immediately

aws rds modify-db-instance --db-instance-identifier <DB_IDENTIFIER> \
  --preferred-maintenance-window "sun:05:00-sun:06:00"

aws rds modify-db-instance --db-instance-identifier <DB_IDENTIFIER> \
  --no-deletion-protection --apply-immediately
```

**`--apply-immediately` causes an outage for changes requiring a restart.** Without it, the change waits for the maintenance window. On production, omit it deliberately rather than by accident.

**The safe pattern: snapshot, then modify, then wait.**

```bash
aws rds create-db-snapshot \
  --db-instance-identifier <DB_IDENTIFIER> \
  --db-snapshot-identifier "<DB_IDENTIFIER>-pre-change-$(date +%Y%m%d%H%M)"
aws rds wait db-snapshot-available --db-snapshot-identifier "<DB_IDENTIFIER>-pre-change-..."

aws rds modify-db-instance --db-instance-identifier <DB_IDENTIFIER> \
  --db-instance-class db.t3.small --apply-immediately
aws rds wait db-instance-available --db-instance-identifier <DB_IDENTIFIER>
```

### 34.1.6 Read Replicas

```bash
aws rds create-db-instance-read-replica \
  --db-instance-identifier <DB_IDENTIFIER>-replica \
  --source-db-instance-identifier <DB_IDENTIFIER>

aws rds promote-read-replica --db-instance-identifier <DB_IDENTIFIER>-replica
```

Promotion is irreversible. The replica becomes a standalone instance and stops replicating.

### 34.1.7 Parameter Groups

```bash
aws rds create-db-parameter-group \
  --db-parameter-group-name <PARAMETER_GROUP_NAME> \
  --db-parameter-group-family mysql8.0 \
  --description "Custom settings"

aws rds modify-db-parameter-group \
  --db-parameter-group-name <PARAMETER_GROUP_NAME> \
  --parameters "ParameterName=max_connections,ParameterValue=200,ApplyMethod=pending-reboot"

aws rds modify-db-instance --db-instance-identifier <DB_IDENTIFIER> \
  --db-parameter-group-name <PARAMETER_GROUP_NAME> --apply-immediately

aws rds reboot-db-instance --db-instance-identifier <DB_IDENTIFIER>
```

`ApplyMethod` is `immediate` for dynamic parameters and `pending-reboot` for static ones. Setting a static parameter to `immediate` fails.

### 34.1.8 Logs, Events, and Metrics

```bash
aws rds describe-db-log-files --db-instance-identifier <DB_IDENTIFIER>

aws rds download-db-log-file-portion \
  --db-instance-identifier <DB_IDENTIFIER> \
  --log-file-name error/mysql-error.log --starting-token 0 --output text

aws rds describe-events --source-identifier <DB_IDENTIFIER> \
  --source-type db-instance --duration 1440

aws cloudwatch get-metric-statistics \
  --namespace AWS/RDS --metric-name CPUUtilization \
  --dimensions Name=DBInstanceIdentifier,Value=<DB_IDENTIFIER> \
  --start-time "$(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%SZ)" \
  --end-time "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --period 300 --statistics Average --output table
```

On macOS, `date -u -d` is not available; use `date -u -v-1H +%Y-%m-%dT%H:%M:%SZ` instead.

### 34.1.9 Cleanup

```bash
# Replicas first, they block deletion of the source
aws rds delete-db-instance --db-instance-identifier <DB_IDENTIFIER>-replica --skip-final-snapshot
aws rds wait db-instance-deleted --db-instance-identifier <DB_IDENTIFIER>-replica

aws rds modify-db-instance --db-instance-identifier <DB_IDENTIFIER> \
  --no-deletion-protection --apply-immediately

aws rds delete-db-instance --db-instance-identifier <DB_IDENTIFIER> \
  --final-db-snapshot-identifier <DB_IDENTIFIER>-final
aws rds wait db-instance-deleted --db-instance-identifier <DB_IDENTIFIER>

aws rds delete-db-subnet-group --db-subnet-group-name <SUBNET_GROUP>
aws rds delete-db-parameter-group --db-parameter-group-name <PARAMETER_GROUP_NAME>
```

Manual snapshots survive instance deletion and keep billing. List and prune them:

```bash
aws rds describe-db-snapshots --snapshot-type manual \
  --query 'DBSnapshots[?SnapshotCreateTime<=`2026-06-01`].DBSnapshotIdentifier' --output text
```

---

## 34.2 AWS Lambda

### 34.2.1 Execution Role

```bash
cat > trust-lambda.json <<'EOF'
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": { "Service": "lambda.amazonaws.com" },
    "Action": "sts:AssumeRole"
  }]
}
EOF

aws iam create-role --role-name <ROLE_NAME> \
  --assume-role-policy-document file://trust-lambda.json

aws iam attach-role-policy --role-name <ROLE_NAME> \
  --policy-arn arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole
```

`AWSLambdaBasicExecutionRole` grants CloudWatch Logs access. Without it the function runs but produces no logs, which makes every subsequent problem harder to diagnose.

### 34.2.2 Package and Create

```bash
cat > lambda_function.py <<'EOF'
import json

def lambda_handler(event, context):
    print(json.dumps(event))
    return {"statusCode": 200, "body": json.dumps({"ok": True})}
EOF

zip function.zip lambda_function.py

aws lambda create-function \
  --function-name <FUNCTION_NAME> \
  --runtime python3.13 \
  --role arn:aws:iam::"$ACCOUNT_ID":role/<ROLE_NAME> \
  --handler lambda_function.lambda_handler \
  --zip-file fileb://function.zip \
  --timeout 30 --memory-size 256 \
  --architectures arm64
```

Note `fileb://` rather than `file://` for binary content. `arm64` selects Graviton, which costs less per GB-second.

Role creation is eventually consistent, so a create immediately after `create-role` can fail with an assume-role error. Retry, or wait a few seconds.

### 34.2.3 Update

```bash
zip function.zip lambda_function.py
aws lambda update-function-code --function-name <FUNCTION_NAME> --zip-file fileb://function.zip
aws lambda wait function-updated --function-name <FUNCTION_NAME>

aws lambda update-function-configuration --function-name <FUNCTION_NAME> \
  --memory-size 512 --timeout 60

aws lambda update-function-configuration --function-name <FUNCTION_NAME> \
  --environment "Variables={LOG_LEVEL=INFO,TABLE_NAME=<TABLE_NAME>}"

aws lambda get-function-configuration --function-name <FUNCTION_NAME>
```

`update-function-configuration` replaces the whole environment variable map. Read the existing values first if you intend to add rather than replace.

### 34.2.4 Versions and Aliases

```bash
VERSION=$(aws lambda publish-version --function-name <FUNCTION_NAME> \
  --query 'Version' --output text)

aws lambda create-alias --function-name <FUNCTION_NAME> \
  --name prod --function-version "$VERSION"

aws lambda update-alias --function-name <FUNCTION_NAME> \
  --name prod --function-version "$VERSION"

# Canary: 10% to the new version
aws lambda update-alias --function-name <FUNCTION_NAME> --name prod \
  --function-version "$VERSION" \
  --routing-config "AdditionalVersionWeights={$OLD_VERSION=0.9}"

aws lambda list-versions-by-function --function-name <FUNCTION_NAME> \
  --query 'Versions[].Version' --output text
```

### 34.2.5 Invoke

```bash
aws lambda invoke --function-name <FUNCTION_NAME> \
  --payload '{"key":"value"}' --cli-binary-format raw-in-base64-out \
  response.json && cat response.json

aws lambda invoke --function-name <FUNCTION_NAME>:prod \
  --payload file://<EVENT_FILE> --cli-binary-format raw-in-base64-out out.json

aws lambda invoke --function-name <FUNCTION_NAME> \
  --invocation-type Event --payload '{}' --cli-binary-format raw-in-base64-out out.json

aws lambda invoke --function-name <FUNCTION_NAME> --invocation-type DryRun out.json
```

`--cli-binary-format raw-in-base64-out` is required in CLI v2 for a plain JSON payload. Omitting it produces an unhelpful base64 decoding error, and it is the most common Lambda CLI complaint.

### 34.2.6 Logs and Metrics

```bash
aws logs tail /aws/lambda/<FUNCTION_NAME> --since 10m --follow

aws logs describe-log-streams --log-group-name /aws/lambda/<FUNCTION_NAME> \
  --order-by LastEventTime --descending --max-items 1

aws cloudwatch get-metric-statistics --namespace AWS/Lambda \
  --metric-name Errors --dimensions Name=FunctionName,Value=<FUNCTION_NAME> \
  --start-time "$(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%SZ)" \
  --end-time "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --period 300 --statistics Sum
```

`aws logs tail --follow` is the single most useful command for debugging a function.

### 34.2.7 Concurrency

```bash
aws lambda put-function-concurrency --function-name <FUNCTION_NAME> \
  --reserved-concurrent-executions 50

aws lambda delete-function-concurrency --function-name <FUNCTION_NAME>

aws lambda put-provisioned-concurrency-config --function-name <FUNCTION_NAME> \
  --qualifier prod --provisioned-concurrent-executions 5

aws lambda get-function-concurrency --function-name <FUNCTION_NAME>
```

Provisioned concurrency applies to a version or alias, never to `$LATEST`.

### 34.2.8 Permissions and Event Sources

```bash
aws lambda add-permission --function-name <FUNCTION_NAME> \
  --statement-id apigw-invoke --action lambda:InvokeFunction \
  --principal apigateway.amazonaws.com \
  --source-arn "arn:aws:execute-api:$REGION:$ACCOUNT_ID:<API_ID>/*/*/*"

aws lambda get-policy --function-name <FUNCTION_NAME> --query Policy --output text | jq .

aws lambda create-event-source-mapping --function-name <FUNCTION_NAME> \
  --event-source-arn "arn:aws:sqs:$REGION:$ACCOUNT_ID:<QUEUE_NAME>" \
  --batch-size 10 --function-response-types ReportBatchItemFailures

aws lambda create-event-source-mapping --function-name <FUNCTION_NAME> \
  --event-source-arn <KINESIS_STREAM_ARN> \
  --starting-position LATEST --batch-size 100 \
  --maximum-retry-attempts 3 --maximum-record-age-in-seconds 3600 \
  --destination-config '{"OnFailure":{"Destination":"<DLQ_ARN>"}}'

aws lambda list-event-source-mappings --function-name <FUNCTION_NAME>
aws lambda delete-event-source-mapping --uuid <UUID>
```

`--function-response-types ReportBatchItemFailures` enables partial batch response on SQS, so one bad message does not return the whole batch. The Kinesis mapping sets the retry and age limits that prevent a poison record blocking the shard, as covered in section 26.3.

### 34.2.9 Layers and VPC

```bash
aws lambda publish-layer-version --layer-name <LAYER_NAME> \
  --zip-file fileb://dependencies.zip --compatible-runtimes python3.13

aws lambda update-function-configuration --function-name <FUNCTION_NAME> \
  --layers "arn:aws:lambda:$REGION:$ACCOUNT_ID:layer:<LAYER_NAME>:1"

aws lambda update-function-configuration --function-name <FUNCTION_NAME> \
  --vpc-config "SubnetIds=<SUBNET_ID_A>,<SUBNET_ID_B>,SecurityGroupIds=<SG_ID>"

# Remove VPC attachment
aws lambda update-function-configuration --function-name <FUNCTION_NAME> \
  --vpc-config "SubnetIds=[],SecurityGroupIds=[]"
```

### 34.2.10 Cleanup

```bash
for uuid in $(aws lambda list-event-source-mappings --function-name <FUNCTION_NAME> \
  --query 'EventSourceMappings[].UUID' --output text); do
  aws lambda delete-event-source-mapping --uuid "$uuid"
done

aws lambda delete-alias --function-name <FUNCTION_NAME> --name prod
aws lambda delete-function --function-name <FUNCTION_NAME>
aws logs delete-log-group --log-group-name /aws/lambda/<FUNCTION_NAME>

aws iam detach-role-policy --role-name <ROLE_NAME> \
  --policy-arn arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole
aws iam delete-role --role-name <ROLE_NAME>
```

Deleting a function does not delete its log group, which retains data and bills indefinitely.

---

## 34.3 Amazon CloudWatch and Logs

### 34.3.1 Metrics

```bash
aws cloudwatch list-metrics --namespace AWS/EC2 \
  --metric-name CPUUtilization --output table

aws cloudwatch get-metric-statistics \
  --namespace AWS/EC2 --metric-name CPUUtilization \
  --dimensions Name=InstanceId,Value=<INSTANCE_ID> \
  --start-time "$(date -u -d '30 minutes ago' +%Y-%m-%dT%H:%M:%SZ)" \
  --end-time "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --period 300 --statistics Average Maximum --output table
```

### 34.3.2 Custom Metrics

```bash
aws cloudwatch put-metric-data --namespace MyApp \
  --metric-name QueueDepth --value 42 --unit Count

aws cloudwatch put-metric-data --namespace MyApp \
  --metric-name ResponseTime --value 0.85 --unit Seconds \
  --dimensions Service=<SERVICE_DIMENSION>,Environment=prod

# High resolution, sub-minute
aws cloudwatch put-metric-data --namespace MyApp \
  --metric-name Latency --value 0.12 --unit Seconds --storage-resolution 1
```

Custom metrics are billed per metric, and each unique dimension combination creates a separate metric. A dimension carrying a user ID or request ID produces an expensive surprise.

### 34.3.3 Alarms

```bash
aws cloudwatch put-metric-alarm \
  --alarm-name <ALARM_NAME> \
  --alarm-description "CPU above 80% for 10 minutes" \
  --namespace AWS/EC2 --metric-name CPUUtilization \
  --dimensions Name=InstanceId,Value=<INSTANCE_ID> \
  --statistic Average --period 300 --evaluation-periods 2 \
  --threshold 80 --comparison-operator GreaterThanThreshold \
  --treat-missing-data notBreaching \
  --alarm-actions "arn:aws:sns:$REGION:$ACCOUNT_ID:<TOPIC_NAME>"

aws cloudwatch put-composite-alarm \
  --alarm-name <ALARM_NAME>-composite \
  --alarm-rule "ALARM(<ALARM_NAME>) AND ALARM(<OTHER_ALARM>)" \
  --alarm-actions "arn:aws:sns:$REGION:$ACCOUNT_ID:<TOPIC_NAME>"

aws cloudwatch describe-alarms --alarm-names <ALARM_NAME>
aws cloudwatch describe-alarms --state-value ALARM \
  --query 'MetricAlarms[].AlarmName' --output text

aws cloudwatch set-alarm-state --alarm-name <ALARM_NAME> \
  --state-value ALARM --state-reason "Testing notification path"

aws cloudwatch disable-alarm-actions --alarm-names <ALARM_NAME>
aws cloudwatch enable-alarm-actions --alarm-names <ALARM_NAME>
aws cloudwatch delete-alarms --alarm-names <ALARM_NAME>
```

`--treat-missing-data` matters. The default `missing` leaves an alarm in `INSUFFICIENT_DATA` when a metric stops reporting, which is exactly when you want to know. `notBreaching` and `breaching` are explicit choices; make one.

`set-alarm-state` is how you test that an alarm actually notifies someone, without waiting for a real breach.

### 34.3.4 Log Groups and Retention

```bash
aws logs describe-log-groups \
  --query 'logGroups[].{Name:logGroupName,Retention:retentionInDays,Bytes:storedBytes}' \
  --output table

aws logs create-log-group --log-group-name <LOG_GROUP_NAME>
aws logs put-retention-policy --log-group-name <LOG_GROUP_NAME> --retention-in-days 30
aws logs delete-log-group --log-group-name <LOG_GROUP_NAME>
```

**Log groups default to never expiring.** Setting retention on every group is one of the easiest recurring savings available. Find the offenders:

```bash
aws logs describe-log-groups \
  --query 'logGroups[?retentionInDays==null].{Name:logGroupName,Bytes:storedBytes}' \
  --output table
```

### 34.3.5 Reading Logs

```bash
aws logs tail <LOG_GROUP_NAME> --since 10m
aws logs tail <LOG_GROUP_NAME> --since 1h --follow --format short

aws logs filter-log-events --log-group-name <LOG_GROUP_NAME> \
  --filter-pattern "ERROR" \
  --start-time $(($(date +%s) - 3600))000 \
  --query 'events[].message' --output text
```

Note that `filter-log-events` takes epoch milliseconds, which is why the shell arithmetic ends in `000`.

### 34.3.6 Logs Insights

```bash
QUERY_ID=$(aws logs start-query \
  --log-group-names <LOG_GROUP_NAME> \
  --start-time $(($(date +%s) - 900)) \
  --end-time $(date +%s) \
  --query-string 'fields @timestamp, @message | filter @message like /ERROR/ | sort @timestamp desc | limit 20' \
  --query 'queryId' --output text)

sleep 5
aws logs get-query-results --query-id "$QUERY_ID"
```

Insights is asynchronous: start the query, then poll for results. `start-time` and `end-time` here are epoch **seconds**, unlike `filter-log-events`, which is an inconsistency worth remembering.

### 34.3.7 Subscriptions, Export, and Dashboards

```bash
aws logs put-subscription-filter \
  --log-group-name <LOG_GROUP_NAME> --filter-name to-lambda \
  --filter-pattern "ERROR" \
  --destination-arn "arn:aws:lambda:$REGION:$ACCOUNT_ID:function:<FUNCTION_NAME>"

aws logs describe-subscription-filters --log-group-name <LOG_GROUP_NAME>
aws logs delete-subscription-filter --log-group-name <LOG_GROUP_NAME> --filter-name to-lambda

aws logs create-export-task --log-group-name <LOG_GROUP_NAME> \
  --from $(($(date +%s) - 86400))000 --to $(date +%s)000 \
  --destination <BUCKET> --destination-prefix exports/

aws cloudwatch put-dashboard --dashboard-name <DASHBOARD_NAME> \
  --dashboard-body file://dashboard.json
aws cloudwatch get-dashboard --dashboard-name <DASHBOARD_NAME>
aws cloudwatch delete-dashboards --dashboard-names <DASHBOARD_NAME>
```

---

## 34.4 Amazon DynamoDB

### 34.4.1 Tables

```bash
aws dynamodb create-table --table-name <TABLE_NAME> \
  --attribute-definitions \
      AttributeName=<PARTITION_KEY>,AttributeType=S \
      AttributeName=<SORT_KEY>,AttributeType=S \
  --key-schema \
      AttributeName=<PARTITION_KEY>,KeyType=HASH \
      AttributeName=<SORT_KEY>,KeyType=RANGE \
  --billing-mode PAY_PER_REQUEST \
  --tags Key=Environment,Value=dev

aws dynamodb wait table-exists --table-name <TABLE_NAME>

aws dynamodb list-tables
aws dynamodb describe-table --table-name <TABLE_NAME> \
  --query 'Table.{Status:TableStatus,Items:ItemCount,Size:TableSizeBytes,Mode:BillingModeSummary.BillingMode}'
```

`--attribute-definitions` declares only the attributes used in keys and indexes. Every other attribute is schemaless and needs no declaration.

### 34.4.2 Capacity and Indexes

```bash
aws dynamodb update-table --table-name <TABLE_NAME> \
  --billing-mode PROVISIONED \
  --provisioned-throughput ReadCapacityUnits=5,WriteCapacityUnits=5

aws dynamodb update-table --table-name <TABLE_NAME> \
  --attribute-definitions AttributeName=<GSI_KEY>,AttributeType=S \
  --global-secondary-index-updates '[{
    "Create": {
      "IndexName": "<GSI_KEY>-index",
      "KeySchema": [{"AttributeName":"<GSI_KEY>","KeyType":"HASH"}],
      "Projection": {"ProjectionType":"ALL"}
    }
  }]'
```

A GSI can be added at any time; a local secondary index only at table creation.

### 34.4.3 Items

```bash
aws dynamodb put-item --table-name <TABLE_NAME> \
  --item '{"<PARTITION_KEY>":{"S":"user#1"},"<SORT_KEY>":{"S":"profile"},"name":{"S":"Maya"},"visits":{"N":"3"}}'

aws dynamodb get-item --table-name <TABLE_NAME> \
  --key '{"<PARTITION_KEY>":{"S":"user#1"},"<SORT_KEY>":{"S":"profile"}}' \
  --consistent-read

aws dynamodb update-item --table-name <TABLE_NAME> \
  --key '{"<PARTITION_KEY>":{"S":"user#1"},"<SORT_KEY>":{"S":"profile"}}' \
  --update-expression "SET #n = :name ADD visits :inc" \
  --expression-attribute-names '{"#n":"name"}' \
  --expression-attribute-values '{":name":{"S":"Maya B"},":inc":{"N":"1"}}' \
  --return-values ALL_NEW

aws dynamodb delete-item --table-name <TABLE_NAME> \
  --key '{"<PARTITION_KEY>":{"S":"user#1"},"<SORT_KEY>":{"S":"profile"}}'
```

`#n` is an expression attribute name, needed because `name` is a reserved word. Any `ValidationException` mentioning a reserved keyword is fixed this way.

**Conditional writes**, which prevent overwriting concurrently:

```bash
aws dynamodb put-item --table-name <TABLE_NAME> \
  --item '{"<PARTITION_KEY>":{"S":"user#2"},"<SORT_KEY>":{"S":"profile"}}' \
  --condition-expression "attribute_not_exists(#pk)" \
  --expression-attribute-names '{"#pk":"<PARTITION_KEY>"}'
```

### 34.4.4 Query and Scan

```bash
aws dynamodb query --table-name <TABLE_NAME> \
  --key-condition-expression "#pk = :pk AND begins_with(#sk, :prefix)" \
  --expression-attribute-names '{"#pk":"<PARTITION_KEY>","#sk":"<SORT_KEY>"}' \
  --expression-attribute-values '{":pk":{"S":"user#1"},":prefix":{"S":"order#"}}'

aws dynamodb query --table-name <TABLE_NAME> --index-name <GSI_KEY>-index \
  --key-condition-expression "#k = :v" \
  --expression-attribute-names '{"#k":"<GSI_KEY>"}' \
  --expression-attribute-values '{":v":{"S":"active"}}'

aws dynamodb scan --table-name <TABLE_NAME> \
  --filter-expression "visits > :n" \
  --expression-attribute-values '{":n":{"N":"5"}}' \
  --max-items 100
```

A `scan` reads every item and then filters, so it consumes capacity for everything it reads regardless of what it returns. Use `query` wherever the access pattern allows.

### 34.4.5 Batch, Transactions, and PartiQL

```bash
aws dynamodb batch-write-item --request-items file://batch.json
aws dynamodb batch-get-item --request-items file://keys.json

aws dynamodb transact-write-items --transact-items file://transaction.json

aws dynamodb execute-statement \
  --statement "SELECT * FROM \"<TABLE_NAME>\" WHERE \"<PARTITION_KEY>\" = 'user#1'"
```

`batch-write-item` handles up to 25 items per call and returns `UnprocessedItems` when throttled, which the caller must retry. It does not fail loudly, so a script ignoring that field silently loses writes.

### 34.4.6 Backups, Streams, and TTL

```bash
aws dynamodb create-backup --table-name <TABLE_NAME> --backup-name <TABLE_NAME>-manual
aws dynamodb list-backups --table-name <TABLE_NAME>
aws dynamodb restore-table-from-backup \
  --target-table-name <TABLE_NAME>-restored --backup-arn <BACKUP_ARN>

aws dynamodb update-continuous-backups --table-name <TABLE_NAME> \
  --point-in-time-recovery-specification PointInTimeRecoveryEnabled=true

aws dynamodb update-table --table-name <TABLE_NAME> \
  --stream-specification StreamEnabled=true,StreamViewType=NEW_AND_OLD_IMAGES

aws dynamodb update-time-to-live --table-name <TABLE_NAME> \
  --time-to-live-specification "Enabled=true,AttributeName=expiresAt"

aws dynamodb delete-table --table-name <TABLE_NAME>
```

Point-in-time recovery is off by default and is what allows restore to any second in the last 35 days.

---

## 34.5 Amazon SNS

```bash
TOPIC_ARN=$(aws sns create-topic --name <TOPIC_NAME> --query 'TopicArn' --output text)

aws sns create-topic --name <TOPIC_NAME>.fifo \
  --attributes FifoTopic=true,ContentBasedDeduplication=true

aws sns list-topics
aws sns get-topic-attributes --topic-arn "$TOPIC_ARN"
```

**Subscriptions**

```bash
aws sns subscribe --topic-arn "$TOPIC_ARN" --protocol email --notification-endpoint <EMAIL>

aws sns subscribe --topic-arn "$TOPIC_ARN" --protocol sqs \
  --notification-endpoint "arn:aws:sqs:$REGION:$ACCOUNT_ID:<QUEUE_NAME>" \
  --attributes RawMessageDelivery=true

aws sns subscribe --topic-arn "$TOPIC_ARN" --protocol lambda \
  --notification-endpoint "arn:aws:lambda:$REGION:$ACCOUNT_ID:function:<FUNCTION_NAME>"

aws sns list-subscriptions-by-topic --topic-arn "$TOPIC_ARN"
aws sns unsubscribe --subscription-arn <SUBSCRIPTION_ARN>
```

An email subscription stays `PendingConfirmation` until the recipient clicks the link. Publishing to a topic whose only subscription is unconfirmed delivers nothing, silently.

`RawMessageDelivery=true` on an SQS subscription delivers the message body directly rather than wrapping it in SNS envelope JSON, which is almost always what the consumer wants.

**Publishing and filtering**

```bash
aws sns publish --topic-arn "$TOPIC_ARN" \
  --subject "Deployment complete" --message "Version 4.2 deployed to production"

aws sns publish --topic-arn "$TOPIC_ARN" --message '{"orderId":"123","status":"shipped"}' \
  --message-attributes '{"eventType":{"DataType":"String","StringValue":"order.shipped"}}'

aws sns set-subscription-attributes \
  --subscription-arn <SUBSCRIPTION_ARN> \
  --attribute-name FilterPolicy \
  --attribute-value '{"eventType":["order.shipped","order.delivered"]}'
```

Filter policies match on message attributes by default, not on the body. To filter on body content, also set `FilterPolicyScope` to `MessageBody`.

**Encryption and cleanup**

```bash
aws sns set-topic-attributes --topic-arn "$TOPIC_ARN" \
  --attribute-name KmsMasterKeyId --attribute-value alias/aws/sns

aws sns delete-topic --topic-arn "$TOPIC_ARN"
```

Deleting a topic deletes its subscriptions.

---

## 34.6 Amazon SQS

```bash
QUEUE_URL=$(aws sqs create-queue --queue-name <QUEUE_NAME> \
  --attributes '{
    "VisibilityTimeout":"120",
    "MessageRetentionPeriod":"345600",
    "ReceiveMessageWaitTimeSeconds":"20"
  }' --query 'QueueUrl' --output text)

aws sqs create-queue --queue-name <QUEUE_NAME>.fifo \
  --attributes '{"FifoQueue":"true","ContentBasedDeduplication":"true"}'

aws sqs list-queues
aws sqs get-queue-url --queue-name <QUEUE_NAME>
aws sqs get-queue-attributes --queue-url "$QUEUE_URL" --attribute-names All
```

`ReceiveMessageWaitTimeSeconds` set to 20 enables long polling at the queue level, which removes almost all empty receives and their cost.

**Dead-letter queue**

```bash
DLQ_URL=$(aws sqs create-queue --queue-name <QUEUE_NAME>-dlq --query 'QueueUrl' --output text)
DLQ_ARN=$(aws sqs get-queue-attributes --queue-url "$DLQ_URL" \
  --attribute-names QueueArn --query 'Attributes.QueueArn' --output text)

aws sqs set-queue-attributes --queue-url "$QUEUE_URL" \
  --attributes "{\"RedrivePolicy\":\"{\\\"deadLetterTargetArn\\\":\\\"$DLQ_ARN\\\",\\\"maxReceiveCount\\\":\\\"5\\\"}\"}"
```

The nested escaping is awkward because `RedrivePolicy` is a JSON string inside a JSON object. Writing it to a file and using `file://` is easier to get right.

**Messages**

```bash
aws sqs send-message --queue-url "$QUEUE_URL" \
  --message-body "Process order 123" \
  --message-attributes '{"priority":{"DataType":"String","StringValue":"high"}}'

aws sqs send-message --queue-url "$FIFO_URL" \
  --message-body "ordered event" --message-group-id orders

aws sqs receive-message --queue-url "$QUEUE_URL" \
  --max-number-of-messages 10 --wait-time-seconds 20 \
  --attribute-names All --message-attribute-names All

aws sqs delete-message --queue-url "$QUEUE_URL" --receipt-handle <RECEIPT_HANDLE>

aws sqs change-message-visibility --queue-url "$QUEUE_URL" \
  --receipt-handle <RECEIPT_HANDLE> --visibility-timeout 300

aws sqs send-message-batch --queue-url "$QUEUE_URL" --entries file://batch.json
aws sqs purge-queue --queue-url "$QUEUE_URL"
aws sqs delete-queue --queue-url "$QUEUE_URL"
```

**A message is not removed by receiving it.** It becomes invisible for the visibility timeout and returns if not deleted. Forgetting `delete-message` is why a queue appears to redeliver everything forever.

`change-message-visibility` extends the timeout for a message still being processed, which is the correct handling for work that occasionally runs long.

**Monitoring**

```bash
aws sqs get-queue-attributes --queue-url "$QUEUE_URL" \
  --attribute-names ApproximateNumberOfMessages ApproximateNumberOfMessagesNotVisible \
                    ApproximateAgeOfOldestMessage
```

`ApproximateAgeOfOldestMessage` is the metric to alarm on. A rising value means consumers are not keeping up, and it detects a stalled consumer that queue depth alone can miss.

---

## 34.7 Putting It Together

A scripted fan-out pipeline: SNS to SQS to Lambda, built entirely from the CLI.

```bash
set -euo pipefail
REGION=us-east-1
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
NAME=demo-pipeline

# 1. Queue and dead-letter queue
DLQ_URL=$(aws sqs create-queue --queue-name "$NAME-dlq" --query QueueUrl --output text)
DLQ_ARN=$(aws sqs get-queue-attributes --queue-url "$DLQ_URL" \
  --attribute-names QueueArn --query Attributes.QueueArn --output text)

QUEUE_URL=$(aws sqs create-queue --queue-name "$NAME-queue" \
  --attributes "{\"VisibilityTimeout\":\"60\",\"ReceiveMessageWaitTimeSeconds\":\"20\",\
\"RedrivePolicy\":\"{\\\"deadLetterTargetArn\\\":\\\"$DLQ_ARN\\\",\\\"maxReceiveCount\\\":\\\"3\\\"}\"}" \
  --query QueueUrl --output text)

QUEUE_ARN=$(aws sqs get-queue-attributes --queue-url "$QUEUE_URL" \
  --attribute-names QueueArn --query Attributes.QueueArn --output text)

# 2. Topic, and allow it to send to the queue
TOPIC_ARN=$(aws sns create-topic --name "$NAME-topic" --query TopicArn --output text)

aws sqs set-queue-attributes --queue-url "$QUEUE_URL" --attributes "{\"Policy\":\"{
  \\\"Version\\\":\\\"2012-10-17\\\",
  \\\"Statement\\\":[{
    \\\"Effect\\\":\\\"Allow\\\",
    \\\"Principal\\\":{\\\"Service\\\":\\\"sns.amazonaws.com\\\"},
    \\\"Action\\\":\\\"sqs:SendMessage\\\",
    \\\"Resource\\\":\\\"$QUEUE_ARN\\\",
    \\\"Condition\\\":{\\\"ArnEquals\\\":{\\\"aws:SourceArn\\\":\\\"$TOPIC_ARN\\\"}}
  }]
}\"}"

aws sns subscribe --topic-arn "$TOPIC_ARN" --protocol sqs \
  --notification-endpoint "$QUEUE_ARN" --attributes RawMessageDelivery=true

# 3. Lambda consumer
aws iam create-role --role-name "$NAME-role" \
  --assume-role-policy-document file://trust-lambda.json
aws iam attach-role-policy --role-name "$NAME-role" \
  --policy-arn arn:aws:iam::aws:policy/service-role/AWSLambdaSQSQueueExecutionRole
sleep 10

zip function.zip lambda_function.py
aws lambda create-function --function-name "$NAME-fn" \
  --runtime python3.13 --role "arn:aws:iam::$ACCOUNT_ID:role/$NAME-role" \
  --handler lambda_function.lambda_handler --zip-file fileb://function.zip \
  --timeout 30 --architectures arm64

aws lambda create-event-source-mapping --function-name "$NAME-fn" \
  --event-source-arn "$QUEUE_ARN" --batch-size 10 \
  --function-response-types ReportBatchItemFailures

# 4. Test
aws sns publish --topic-arn "$TOPIC_ARN" --message '{"orderId":"123"}'
sleep 10
aws logs tail "/aws/lambda/$NAME-fn" --since 5m
```

Three things this demonstrates that a console walkthrough hides: the queue policy is what actually permits SNS to deliver, `AWSLambdaSQSQueueExecutionRole` is needed rather than the basic execution role because the function must call `ReceiveMessage` and `DeleteMessage`, and the `sleep 10` exists because IAM role propagation is eventually consistent.

**Teardown**

```bash
aws lambda delete-event-source-mapping --uuid <UUID>
aws lambda delete-function --function-name "$NAME-fn"
aws sns delete-topic --topic-arn "$TOPIC_ARN"
aws sqs delete-queue --queue-url "$QUEUE_URL"
aws sqs delete-queue --queue-url "$DLQ_URL"
aws iam detach-role-policy --role-name "$NAME-role" \
  --policy-arn arn:aws:iam::aws:policy/service-role/AWSLambdaSQSQueueExecutionRole
aws iam delete-role --role-name "$NAME-role"
aws logs delete-log-group --log-group-name "/aws/lambda/$NAME-fn"
```

---

## 34.8 End-of-Chapter Questions

**Q1.** An `aws lambda invoke` command with `--payload '{"key":"value"}'` fails with a base64 decoding error in CLI v2. What is missing?

- A. The payload must be written to a file
- B. `--cli-binary-format raw-in-base64-out`
- C. The function must be published as a version first
- D. `--invocation-type Event`

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* CLI v2 expects base64 by default, and this flag tells it to accept raw JSON.

**Q2.** An SQS consumer processes the same messages repeatedly and the queue never empties. What is the most likely cause?

- A. Long polling is disabled
- B. The consumer is not calling `delete-message` after processing
- C. The queue is FIFO
- D. The retention period is too short

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Receiving a message only hides it for the visibility timeout; it returns to the queue unless explicitly deleted.

**Q3.** A DynamoDB `update-item` fails with a validation error about a reserved keyword. What resolves it?

- A. Rename the table
- B. Use an expression attribute name such as `#n` mapped to the attribute
- C. Switch to `put-item`
- D. Enable point-in-time recovery

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Reserved words cannot appear directly in expressions and must be substituted through `--expression-attribute-names`.

**Q4.** An engineer runs `aws rds modify-db-instance --db-instance-class db.r6g.large --apply-immediately` on production during business hours. What is the consequence?

- A. The change queues until the next maintenance window
- B. The instance restarts immediately, causing an outage
- C. The command is rejected without a snapshot
- D. Only the parameter group is updated

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* `--apply-immediately` triggers changes requiring a restart straight away; omitting it defers them to the maintenance window.

**Q5.** CloudWatch log storage costs are growing steadily across many log groups. What is the most likely cause and the fix?

- A. Detailed monitoring is enabled; disable it
- B. Log groups default to never expiring; set a retention policy on each
- C. Too many custom metrics; reduce dimensions
- D. Logs Insights queries are being retained

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Log groups retain data indefinitely unless `put-retention-policy` is applied, which is a common and easily fixed recurring cost.

**Q6.** An SNS topic has one email subscription and publishing produces no delivery. What should be checked first?

- A. Whether the topic is FIFO
- B. Whether the subscription is still `PendingConfirmation` because the recipient has not clicked the confirmation link
- C. Whether a filter policy is applied
- D. Whether the topic is encrypted

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* Unconfirmed email subscriptions receive nothing, and SNS reports the publish as successful regardless.
