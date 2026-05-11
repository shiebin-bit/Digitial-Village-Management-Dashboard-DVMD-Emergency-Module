variable "project_id" {
  description = "Google Cloud project ID."
  type        = string
}

variable "region" {
  description = "Google Cloud region."
  type        = string
  default     = "asia-southeast1"
}

variable "zone" {
  description = "Google Cloud zone."
  type        = string
  default     = "asia-southeast1-a"
}

variable "name_prefix" {
  description = "Prefix for GCP resources."
  type        = string
  default     = "dvmd"
}

variable "machine_type" {
  description = "Compute Engine machine type for the single-node k3s VM."
  type        = string
  default     = "e2-medium"
}

variable "network_name" {
  description = "VPC network name."
  type        = string
  default     = "default"
}

variable "ssh_user" {
  description = "Linux SSH user created through instance metadata."
  type        = string
  default     = "ubuntu"
}

variable "ssh_public_key" {
  description = "Public SSH key injected into the VM."
  type        = string
  sensitive   = true
}

variable "allowed_source_ranges" {
  description = "CIDR ranges allowed to reach SSH and the app NodePort."
  type        = list(string)
  default     = ["0.0.0.0/0"]
}

