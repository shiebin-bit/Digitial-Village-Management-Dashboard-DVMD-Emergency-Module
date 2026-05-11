resource "google_compute_firewall" "allow_ssh_and_app" {
  name    = "${var.name_prefix}-allow-ssh-app"
  network = var.network_name

  allow {
    protocol = "tcp"
    ports    = ["22", "30081"]
  }

  source_ranges = var.allowed_source_ranges
  target_tags   = ["${var.name_prefix}-k3s"]
}

resource "google_compute_instance" "k3s" {
  name         = "${var.name_prefix}-k3s"
  machine_type = var.machine_type
  zone         = var.zone
  tags         = ["${var.name_prefix}-k3s"]

  boot_disk {
    initialize_params {
      image = "ubuntu-os-cloud/ubuntu-2204-lts"
      size  = 20
      type  = "pd-balanced"
    }
  }

  network_interface {
    network = var.network_name

    access_config {
    }
  }

  metadata = {
    ssh-keys       = "${var.ssh_user}:${var.ssh_public_key}"
    startup-script = file("${path.module}/startup-k3s.sh")
  }

  service_account {
    scopes = ["cloud-platform"]
  }
}

