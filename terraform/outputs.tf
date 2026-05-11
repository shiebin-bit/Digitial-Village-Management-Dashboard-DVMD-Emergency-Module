output "instance_name" {
  description = "GCE k3s VM instance name."
  value       = google_compute_instance.k3s.name
}

output "instance_external_ip" {
  description = "External IP for the k3s VM."
  value       = google_compute_instance.k3s.network_interface[0].access_config[0].nat_ip
}

output "application_url" {
  description = "DVMD NodePort URL."
  value       = "http://${google_compute_instance.k3s.network_interface[0].access_config[0].nat_ip}:30081/backend/loginpage.php"
}

