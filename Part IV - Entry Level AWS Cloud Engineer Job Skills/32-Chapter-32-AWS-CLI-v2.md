# Chapter 32: AWS CLI v2

---

The CLI is the primary tool for the rest of Part IV. The lifecycle is: install, configure, authenticate including MFA and role assumption, verify, and troubleshoot.

**Supported platforms**

- Linux on x86_64 and aarch64: Ubuntu, Debian, RHEL, CentOS, Amazon Linux.
- macOS on Intel and Apple Silicon.
- Windows 10 and 11, 64-bit, through PowerShell or Command Prompt.

**Prerequisites**

- An AWS account with an IAM user, federated access, or a role you can assume.
- Administrative or `sudo` rights to install.
- `curl` and `unzip` for the Linux and macOS manual install.
- `jq`, optional but used throughout this part for parsing JSON.
- A terminal: `bash`, `zsh`, or PowerShell.

---

## 32.1 Installing the CLI

Follow only the section matching your operating system.

### 32.1.1 Linux

1. Update packages.
   - Debian or Ubuntu:
     ```bash
     sudo apt update && sudo apt upgrade -y
     ```
   - RHEL, CentOS, or Amazon Linux 2:
     ```bash
     sudo yum update -y
     ```
   - Amazon Linux 2023 and current Fedora, which use `dnf`:
     ```bash
     sudo dnf update -y
     ```
2. Install the prerequisites.
   - Debian or Ubuntu:
     ```bash
     sudo apt install -y curl unzip
     ```
   - RHEL, CentOS, or Amazon Linux:
     ```bash
     sudo dnf install -y curl unzip
     ```
3. Check the processor architecture.
   ```bash
   uname -m
   ```
   Common outputs are `x86_64` and `aarch64`.
4. Download the installer for that architecture.
   - x86_64:
     ```bash
     curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
     ```
   - aarch64:
     ```bash
     curl "https://awscli.amazonaws.com/awscli-exe-linux-aarch64.zip" -o "awscliv2.zip"
     ```
5. Unzip the archive.
   ```bash
   unzip awscliv2.zip
   ```
6. Run the installer.
   ```bash
   sudo ./aws/install
   ```
   Files go to `/usr/local/aws-cli` with a symlink in `/usr/local/bin`. To choose different paths:
   ```bash
   sudo ./aws/install -i /usr/local/aws-cli -b /usr/local/bin
   ```
7. Verify.
   ```bash
   aws --version
   ```

![AWS CLI version output on Linux](https://github.com/user-attachments/assets/d25e5577-5408-4687-a931-6250920e3169)

**To uninstall on Linux**

```bash
sudo rm -rf /usr/local/aws-cli
sudo rm /usr/local/bin/aws /usr/local/bin/aws_completer 2>/dev/null || true
```

### 32.1.2 macOS

1. Download the package.
   ```zsh
   curl "https://awscli.amazonaws.com/AWSCLIV2.pkg" -o "AWSCLIV2.pkg"
   ```
2. Install it.
   ```zsh
   sudo installer -pkg AWSCLIV2.pkg -target /
   ```
3. Verify.
   ```zsh
   aws --version
   ```

![AWS CLI version output on macOS](https://github.com/user-attachments/assets/bd8bf054-9e55-421f-91c6-299b8f1b02b1)

**To uninstall on macOS**

```zsh
sudo rm -rf /usr/local/aws-cli
sudo rm /usr/local/bin/aws /usr/local/bin/aws_completer 2>/dev/null || true
```

### 32.1.3 Windows

**Method A, the MSI installer**

1. Open PowerShell as Administrator.
2. Run the installer and follow the wizard.
   ```powershell
   msiexec.exe /i https://awscli.amazonaws.com/AWSCLIV2.msi
   ```
3. Verify.
   ```powershell
   aws --version
   ```

**Method B, winget**

1. Check the Windows version.
   ```powershell
   winver
   ```
2. Check the App Installer version.
   ```powershell
   winget --version
   ```
3. Update the source list.
   ```powershell
   winget source update
   ```
4. Install the CLI.
   ```powershell
   winget install Amazon.AWSCLI
   ```
5. Verify.
   ```powershell
   aws --version
   ```

**To uninstall on Windows**

- With winget:
  ```powershell
  winget uninstall Amazon.AWSCLI
  ```
- With the MSI: run the same `msiexec` command and choose **Remove**, or use **Control Panel**, **Programs**, **Uninstall a program**.
- To remove stored profiles as well, which is optional and destroys your credentials:
  ```powershell
  Remove-Item -Recurse -Force "$env:USERPROFILE\.aws"
  ```

**If `aws` is not recognized after installing on Windows**

1. Close every open PowerShell window. PATH changes do not apply to shells that were already open.
2. Confirm the install directory exists at `C:\Program Files\Amazon\AWSCLIV2\`.
3. If it is missing from PATH, press **Win + R**, type `sysdm.cpl`, and press Enter.
4. Open **Advanced**, then **Environment Variables**.
5. Under **System variables**, select **Path** and choose **Edit**.
6. Choose **New** and enter `C:\Program Files\Amazon\AWSCLIV2\`.
7. Choose **OK** on each dialog and open a new terminal.

### 32.1.4 Post-Install Verification

1. Confirm the version.
   ```bash
   aws --version
   ```
2. Confirm the identity. This fails until credentials are configured, which is expected at this point.
   ```bash
   aws sts get-caller-identity
   ```

![aws sts get-caller-identity output](https://github.com/user-attachments/assets/4ca1f37a-70a8-4ae1-9ff9-e041677a8471)

3. Confirm connectivity with a call that returns data.
   ```bash
   aws ec2 describe-regions --output table
   ```

![aws ec2 describe-regions table output](https://github.com/user-attachments/assets/a4e2d833-2727-4322-b57a-69fb2b15346e)

---

## 32.2 Configuration

The CLI stores settings in two files:

| File | Linux and macOS | Windows |
| --- | --- | --- |
| Credentials | `~/.aws/credentials` | `%UserProfile%\.aws\credentials` |
| Configuration | `~/.aws/config` | `%UserProfile%\.aws\config` |

![AWS credentials file location](https://github.com/user-attachments/assets/445d3408-f255-412d-8982-01212c8950b9)

![AWS config file location](https://github.com/user-attachments/assets/3f7b1cd2-fcc6-4633-88f0-bb9ccfabe808)

### 32.2.1 Interactive Setup

1. Run the configure command.
   ```bash
   aws configure
   ```
2. Enter the **AWS Access Key ID**.
3. Enter the **AWS Secret Access Key**.
4. Enter the **Default region name**, for example `us-east-1`.
5. Enter the **Default output format**: `json`, `yaml`, `text`, or `table`.

![aws configure interactive prompts](https://github.com/user-attachments/assets/a4c24ca2-b975-4e83-b618-fd6b65150f65)

![Completed aws configure session](https://github.com/user-attachments/assets/ebab6f6e-a22d-47b4-b035-6a6f40eee9a7)

**If your access key ID begins with `ASIA`,** you have temporary STS credentials and a session token is also required. `aws configure` does not prompt for one, so it must be added manually. Keys beginning `AKIA` are long-lived and need no token.

![Temporary credentials including a session token](https://github.com/user-attachments/assets/6693392a-e46c-4634-89e5-599a93c2c5b7)

6. Open the credentials file.
   - Linux or macOS:
     ```bash
     vi ~/.aws/credentials
     ```
   - Windows:
     ```powershell
     notepad $env:UserProfile\.aws\credentials
     ```
7. Add the session token under the relevant profile.
   ```ini
   aws_session_token = <SESSION_TOKEN>
   ```
8. Verify.
   ```bash
   aws sts get-caller-identity
   ```

### 32.2.2 File Format

`~/.aws/credentials`:

```ini
[default]
aws_access_key_id = AKIA_DEFAULT...
aws_secret_access_key = defaultSecret...

[prod]
aws_access_key_id = AKIA_PROD...
aws_secret_access_key = prodSecret...
```

`~/.aws/config`:

```ini
[default]
region = us-east-1
output = json

[profile prod]
region = us-west-2
output = json
```

Note the asymmetry: named profiles require the `profile` prefix in `config` but not in `credentials`. Getting this wrong produces a profile the CLI cannot find, and it is a common first-hour mistake.

### 32.2.3 Named Profiles

1. Create a profile.
   ```bash
   aws configure --profile prod
   ```
2. Use it for a single command.
   ```bash
   aws s3 ls --profile prod
   ```
3. Set it for the whole shell session.
   - Linux or macOS:
     ```bash
     export AWS_PROFILE=prod
     ```
   - PowerShell:
     ```powershell
     $Env:AWS_PROFILE = 'prod'
     ```
4. Confirm which identity is active.
   ```bash
   aws sts get-caller-identity
   ```

### 32.2.4 IAM Identity Center Profiles

Where the organization uses IAM Identity Center, which section 17.5.1 covers, configure an SSO profile instead of storing keys.

1. Start the guided setup.
   ```bash
   aws configure sso
   ```
2. Enter the SSO start URL and Region when prompted.
3. Complete authorization in the browser window that opens.
4. Select the account and permission set.
5. Name the profile.
6. Sign in when the session expires.
   ```bash
   aws sso login --profile <PROFILE>
   ```

This is the preferred production setup, because no long-lived secret is written to disk.

---

## 32.3 Environment Variables and Precedence

Environment variables override profile settings for the current session without editing any file.

**Bash**

```bash
export AWS_ACCESS_KEY_ID="AKIA..."
export AWS_SECRET_ACCESS_KEY="..."
export AWS_SESSION_TOKEN="..."          # temporary credentials only
export AWS_REGION="us-east-1"
```

**PowerShell**

```powershell
$Env:AWS_ACCESS_KEY_ID     = "AKIA..."
$Env:AWS_SECRET_ACCESS_KEY = "..."
$Env:AWS_SESSION_TOKEN     = "..."
$Env:AWS_REGION            = "us-east-1"
```

`AWS_DEFAULT_REGION` is also honored and is the older name. `AWS_REGION` is preferred in v2 and is what the SDKs use.

**Clearing them**

- Linux or macOS:
  ```bash
  unset AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_SESSION_TOKEN AWS_PROFILE AWS_REGION
  ```
- PowerShell:
  ```powershell
  "ACCESS_KEY_ID","SECRET_ACCESS_KEY","SESSION_TOKEN","PROFILE","REGION","DEFAULT_REGION" |
    ForEach-Object { Remove-Item "Env:AWS_$_" -ErrorAction SilentlyContinue }
  ```

**Profiles compared with environment variables**

| Aspect | Profiles | Environment variables |
| --- | --- | --- |
| Persistence | Stored on disk | Session only |
| Typical use | Day-to-day contexts | CI/CD, temporary STS sessions |
| Precedence | Lower | Higher, overriding profiles |
| Rotation | Edit the file | Swap the variable |

Use profiles for stable recurring contexts, and environment variables in pipelines or with temporary credentials. Note that command line options such as `--profile` outrank both, as covered in section 31.3.

**The failure this causes.** An exported `AWS_ACCESS_KEY_ID` left over from an earlier task silently overrides `--profile` on later commands. When output looks wrong, run `env | grep AWS` first.

---

## 32.4 MFA and Temporary Credentials

### 32.4.1 get-session-token, Remaining the Same Identity

Use this when policies require MFA but the effective identity does not change.

1. Request the session.
   ```bash
   aws sts get-session-token \
     --serial-number arn:aws:iam::<AWS_ACCOUNT_ID>:mfa/<USER_NAME> \
     --token-code 123456 \
     --duration-seconds 3600
   ```
2. Read the response.
   ```json
   {
     "Credentials": {
       "AccessKeyId": "ASIA....",
       "SecretAccessKey": "abcd...",
       "SessionToken": "IQoJb3...",
       "Expiration": "2026-08-17T12:34:56Z"
     }
   }
   ```
3. Either export the values.
   ```bash
   export AWS_ACCESS_KEY_ID="ASIA..."
   export AWS_SECRET_ACCESS_KEY="abcd..."
   export AWS_SESSION_TOKEN="IQoJb3..."
   ```
4. Or write them to a named profile.
   ```bash
   aws configure set aws_access_key_id     "ASIA..."   --profile mfa
   aws configure set aws_secret_access_key "abcd..."   --profile mfa
   aws configure set aws_session_token     "IQoJb3..." --profile mfa
   ```
5. Use the profile.
   ```bash
   aws s3 ls --profile mfa
   ```

### 32.4.2 assume-role, Changing Identity

Use this for cross-account access, elevated privilege, or separation of duties.

1. Assume the role.
   ```bash
   aws sts assume-role \
     --role-arn arn:aws:iam::<AWS_ACCOUNT_ID>:role/<ROLE_NAME> \
     --role-session-name mySession \
     --serial-number arn:aws:iam::<AWS_ACCOUNT_ID>:mfa/<USER_NAME> \
     --token-code 123456
   ```
2. Extract and export the credentials with `jq`.
   ```bash
   CREDS_JSON=$(aws sts assume-role \
     --role-arn arn:aws:iam::<AWS_ACCOUNT_ID>:role/<ROLE_NAME> \
     --role-session-name mySession)

   export AWS_ACCESS_KEY_ID=$(echo "$CREDS_JSON"     | jq -r '.Credentials.AccessKeyId')
   export AWS_SECRET_ACCESS_KEY=$(echo "$CREDS_JSON" | jq -r '.Credentials.SecretAccessKey')
   export AWS_SESSION_TOKEN=$(echo "$CREDS_JSON"     | jq -r '.Credentials.SessionToken')
   ```
3. Or in PowerShell.
   ```powershell
   $creds = aws sts assume-role `
     --role-arn arn:aws:iam::<AWS_ACCOUNT_ID>:role/<ROLE_NAME> `
     --role-session-name mySession | ConvertFrom-Json

   $Env:AWS_ACCESS_KEY_ID     = $creds.Credentials.AccessKeyId
   $Env:AWS_SECRET_ACCESS_KEY = $creds.Credentials.SecretAccessKey
   $Env:AWS_SESSION_TOKEN     = $creds.Credentials.SessionToken
   ```
4. Confirm the identity changed.
   ```bash
   aws sts get-caller-identity
   ```

**A better alternative to steps 2 and 3.** Define the role in `~/.aws/config` and let the CLI assume it automatically:

```ini
[profile prod-admin]
role_arn = arn:aws:iam::<AWS_ACCOUNT_ID>:role/<ROLE_NAME>
source_profile = default
mfa_serial = arn:aws:iam::<AWS_ACCOUNT_ID>:mfa/<USER_NAME>
region = us-east-1
```

The CLI then prompts for the MFA code when needed, caches the session, and refreshes it, with no exporting at all.

### 32.4.3 Comparison

| Feature | get-session-token | assume-role |
| --- | --- | --- |
| Resulting identity | The same IAM user | The role, a different principal |
| Permissions | Those of the IAM user | Those of the role |
| Typical use | Adding MFA to a user's own access | Cross-account or elevated access |
| Maximum duration | Up to 36 hours for an IAM user | Up to 12 hours, capped by the role's `MaxSessionDuration` |
| MFA | Supplied at call time | Enforced through the role trust policy |
| External ID | Not applicable | Supported |

---

## 32.5 Output, Filtering, and Querying

**Output formats.** Set globally with `--output` or per profile: `json` for scripting, `yaml` for readability, `table` for human inspection, and `text` for line-oriented shell processing.

**`--query` uses JMESPath**, applied by the CLI before output, which is faster than piping everything to `jq` and works identically on Windows.

1. Select one field from each item.
   ```bash
   aws ec2 describe-instances \
     --query 'Reservations[].Instances[].InstanceId'
   ```
2. Build a named structure.
   ```bash
   aws ec2 describe-instances \
     --query 'Reservations[].Instances[].{ID:InstanceId,Type:InstanceType,State:State.Name}' \
     --output table
   ```
3. Filter on a value.
   ```bash
   aws ec2 describe-instances \
     --query 'Reservations[].Instances[?State.Name==`running`].InstanceId' \
     --output text
   ```
4. Read a tag, which requires a nested filter because tags are a list.
   ```bash
   aws ec2 describe-instances \
     --query 'Reservations[].Instances[].{ID:InstanceId,Name:Tags[?Key==`Name`]|[0].Value}' \
     --output table
   ```
5. Sort and take the newest.
   ```bash
   aws ec2 describe-images --owners amazon \
     --filters "Name=name,Values=al2023-ami-*-x86_64" \
     --query 'sort_by(Images,&CreationDate)[-1].ImageId' \
     --output text
   ```

**`--filters` versus `--query`.** Filters are applied server side by the service, so they reduce what is returned and are faster. Queries are applied client side after the response arrives. Filter first, then query.

**Pagination.** The CLI paginates automatically. Control it with:

```bash
aws s3api list-objects-v2 --bucket <BUCKET> --max-items 100
aws s3api list-objects-v2 --bucket <BUCKET> --page-size 100 --no-paginate
```

**Suppressing the pager.** Output opening in `less` breaks scripts. Disable per command with `--no-cli-pager`, or set it permanently:

```bash
aws configure set cli_pager ""
```

**Command completion** saves a great deal of typing.

```bash
complete -C '/usr/local/bin/aws_completer' aws
```

Add that line to `~/.bashrc` or `~/.zshrc` to make it persistent.

**Dry runs.** Many EC2 commands accept `--dry-run`, which checks permissions and parameters without acting. Use it before anything destructive.

---

## 32.6 Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| `Unable to locate credentials` | No profile, environment variable, or instance role resolved. Run `aws configure` or check `AWS_PROFILE` |
| `The security token included in the request is invalid` | Temporary credentials expired, or the session token is missing for an `ASIA` key. Reissue them |
| `The config profile (x) could not be found` | The profile is missing the `profile` prefix in `~/.aws/config` |
| `AccessDenied` naming an action | The identity lacks that permission. Read the ARN in the message; it names the exact action and resource |
| `AccessDenied` with MFA required | The policy has an MFA condition. Use `get-session-token` or an assume-role profile with `mfa_serial` |
| Commands run against the wrong account | An environment variable is overriding the profile. Run `env \| grep AWS` and `aws sts get-caller-identity` |
| `Could not connect to the endpoint URL` | Wrong Region, a service unavailable in that Region, or no network path. Check the Region first |
| Resource "does not exist" but is visible in the console | The Region differs between the CLI and the console session |
| `aws: command not found` on Windows | PATH not refreshed. Close every shell and open a new one |
| Output opens in a pager and blocks a script | Add `--no-cli-pager` or set `cli_pager` to empty |
| `RequestLimitExceeded` or `Throttling` | API rate limiting. Retry with backoff; the CLI retries automatically but scripts calling in tight loops need their own delay |

**Debugging a command.** Add `--debug` for full request and response detail, including the credentials chain resolution and the signed request. The output is verbose; the useful part is usually near the top, where it reports which credential provider was used.

**Recommendations**

- Prefer roles with MFA over long-lived access keys.
- Keep separate profiles for read-only, development, and production, and name them so a mistake is obvious.
- Never hardcode credentials in scripts; use environment variables or a secrets manager in CI/CD.
- Rotate access keys regularly and delete unused ones.
- State `--profile` explicitly in automation, rather than relying on whatever is default.
- Use `--dry-run` where supported before running anything destructive.
- Use `aws configure sso` where the organization uses IAM Identity Center.

---

## 32.7 End-of-Chapter Questions

**Q1.** An access key ID begins with `ASIA` and commands fail with an invalid security token error. What is missing?

- A. The default Region
- B. The session token, since `ASIA` indicates temporary STS credentials
- C. An MFA device
- D. The `profile` prefix in the config file

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* Keys beginning `ASIA` are temporary and require `aws_session_token` alongside the key and secret; `aws configure` does not prompt for it.

**Q2.** A named profile works when referenced from `~/.aws/credentials` but the CLI reports it cannot be found when settings are added to `~/.aws/config`. What is wrong?

- A. The config file must be in JSON format
- B. Named profiles in `~/.aws/config` require the `profile` prefix, as in `[profile prod]`
- C. Profiles cannot set a Region
- D. The credentials file takes precedence

**Answer: B.** *Target exam: AWS Certified Cloud Practitioner.* The two files use different section naming, which is the most common first-hour configuration error.

**Q3.** Which command should be run first whenever CLI output is unexpected?

- A. `aws --version`
- B. `aws configure list-profiles`
- C. `aws sts get-caller-identity`
- D. `aws ec2 describe-regions`

**Answer: C.** *Target exam: AWS Certified Solutions Architect - Associate.* It reports the account, user ID, and ARN actually in use, which resolves questions about which credentials the command resolved.

**Q4.** An engineer needs to assume a role in another account with MFA, repeatedly, without exporting credentials by hand each time. What is the cleanest approach?

- A. Run `aws sts assume-role` and export the three values before each session
- B. Define a profile in `~/.aws/config` with `role_arn`, `source_profile`, and `mfa_serial`, letting the CLI assume and cache the session
- C. Store the role's credentials in `~/.aws/credentials`
- D. Use `get-session-token` instead

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* The CLI prompts for the MFA code, assumes the role, caches the session, and refreshes it automatically, with nothing exported.

**Q5.** A script listing thousands of EC2 instances is slow, and it retrieves all instances before filtering for running ones locally. What improves it most?

- A. Change the output format to text
- B. Use `--filters` so the service returns only running instances, rather than filtering client side with `--query`
- C. Increase `--page-size`
- D. Add `--no-cli-pager`

**Answer: B.** *Target exam: AWS Certified Solutions Architect - Associate.* Filters are applied server side and reduce what is transferred; queries run client side after the full response arrives.
