# Host Repo on Google Cloud VM Setup Plan

This plan outlines the steps required to set up and host this repository on a Google Cloud Compute Engine Virtual Machine (VM) for running and testing the Laravel application.

## 1. Prerequisites
- A Google Cloud Platform (GCP) account.
- A created Google Cloud project with billing enabled.
- The `gcloud` CLI installed locally and authenticated (`gcloud auth login`).

## 2. Create the Compute Engine VM
1. Go to the **Compute Engine -> VM instances** page in the GCP Console.
2. Click **Create Instance**.
3. **Machine configuration**: Choose a machine type suitable for running Laravel and a MySQL database (e.g., `e2-medium` or `e2-standard-2`).
4. **Boot disk**: Change the boot disk to **Ubuntu 24.04 LTS** (or 22.04 LTS) with at least **20 GB** standard persistent disk.
5. **Firewall**: Check **Allow HTTP traffic** and **Allow HTTPS traffic**.
6. Click **Create**.

## 3. SSH into the VM and Install Dependencies
Once the VM is running, SSH into it (using the `SSH` button in the console or `gcloud compute ssh <instance-name>`).

Run the following commands to install required dependencies (PHP 8.3+, Composer, MySQL, Nginx, Node.js):

```bash
sudo apt update && sudo apt upgrade -y

# Install Nginx and MySQL
sudo apt install -y nginx mysql-server curl unzip git

# Install PHP 8.3 and extensions (Ubuntu 24.04 includes PHP 8.3 by default)
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath php8.3-sqlite3

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js & npm (for building the frontend)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

## 4. Setup MySQL Database
Since the application uses MySQL for testing (and requires it for full-text search features during migrations), set up the database:

```bash
sudo mysql -u root

# Inside MySQL prompt:
CREATE DATABASE popmart_tracker;
CREATE USER 'popmart'@'localhost' IDENTIFIED BY 'secretpassword';
GRANT ALL PRIVILEGES ON popmart_tracker.* TO 'popmart'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 5. Clone the Repository & Configure the Application
```bash
# Clone the repository (you might need to setup SSH keys on the VM for GitHub access)
git clone <your-repo-url> popmart-tracker
cd popmart-tracker

# Install PHP dependencies
composer install --no-dev --optimize-autoloader # remove --no-dev if running tests

# Setup Environment
cp .env.example .env
php artisan key:generate

# Update .env to use MySQL database credentials created earlier:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=popmart_tracker
# DB_USERNAME=popmart
# DB_PASSWORD=secretpassword

# Run Migrations
php artisan migrate

# Install Node modules and build frontend
npm install
npm run build
```

## 6. Configure Nginx (Optional, if exposing to the web)
Create a new Nginx configuration file at `/etc/nginx/sites-available/popmart` and add the following configuration:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name <vm-external-ip>;
    root /home/<your-username>/popmart-tracker/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/popmart /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo systemctl restart nginx
```

## 7. Setup Directory Permissions
```bash
sudo chown -R www-data:www-data /home/<your-username>/popmart-tracker/storage
sudo chown -R www-data:www-data /home/<your-username>/popmart-tracker/bootstrap/cache
```

## 8. Run Tests on the VM
Once everything is configured, run tests using pest:
```bash
php artisan test
```
*Note: Ensure `DB_CONNECTION=mysql` in your `.env` to prevent the SQLite full-text indexing errors that occur locally.*
