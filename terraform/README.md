# Google Cloud k3s Terraform

This Terraform configuration creates one Google Compute Engine VM and installs k3s through the startup script.

It does not create Google Artifact Registry because this project publishes the Docker image to GHCR.

## Required Inputs

Set these through GitHub Actions variables/secrets:

```text
GCP_PROJECT_ID
TF_STATE_BUCKET
GCE_SSH_PUBLIC_KEY
GCE_SSH_PRIVATE_KEY
GCP_SA_KEY
```

Create the `TF_STATE_BUCKET` GCS bucket before the first workflow run. The GitHub Actions service account needs permission to read and write Terraform state objects in that bucket.

The firewall opens:

```text
22     SSH
30081  DVMD NodePort
```

After deployment, open:

```text
http://<instance_external_ip>:30081/backend/loginpage.php
```
