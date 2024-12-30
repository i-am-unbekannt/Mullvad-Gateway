# Mullvad Gateway Setup Guide

This guide will walk you through building and configuring a Mullvad Gateway network. The following prerequisites and steps are required to successfully set up the gateway.

## Prerequisites

1. **Mullvad Account**
   - Create an account at [Mullvad](https://mullvad.net/en/account/create).

2. **Pre-configured Network**
   - A network with a DHCP/DNS server already set up.

3. **Linux Virtual Machine**
   - Freshly installed Linux VM (tested on Ubuntu 22.04.3 LTS - recommended).

### Notes

- **IPv4 Only:** IPv6 is not supported.
- **Static IP Required:** Ensure the VM has a static IP address. 
  - Example configuration:
    - Test network: Managed on a WatchGuard firewall.
    - Firewall IP: `10.0.41.1`
    - Mullvad Gateway VM static IP: `10.0.41.254`
    - Gateway (for clients): `10.0.41.254`
    - DNS Server (for clients): `10.0.41.1`
- All traffic (except DNS) will route through the Mullvad VPN to prevent slow connections.

---

## Steps to Set Up the Mullvad Gateway

### 1. Install Mullvad CLI and Log In

Run the following commands to install Mullvad CLI:

```bash
# Download the Mullvad signing key
sudo curl -fsSLo /usr/share/keyrings/mullvad-keyring.asc https://repository.mullvad.net/deb/mullvad-keyring.asc

# Add the Mullvad repository server to apt
echo "deb [signed-by=/usr/share/keyrings/mullvad-keyring.asc arch=$( dpkg --print-architecture )] https://repository.mullvad.net/deb/stable $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/mullvad.list

# Install the package
sudo apt update
sudo apt install mullvad-vpn -y

# Log in to your Mullvad account
mullvad account login <YOUR_ACCOUNT_NUMBER>

# Allow LAN access and connect to Mullvad VPN
mullvad lan set allow
mullvad connect
mullvad status
```

### 2. Enable IPv4 Forwarding

Enable IP forwarding to route traffic through the gateway:

```bash
echo "net.ipv4.ip_forward=1" | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

### 3. Configure NAT Rules

Set up NAT rules to forward traffic from your network interface to the Mullvad VPN interface. Replace `<NETWORK-INTERFACE>` and `<MULLVAD-INTERFACE>` with the correct names from your system (check interface names with `ip a`).

```bash
# Add NAT rules
sudo iptables -t nat -A POSTROUTING -o <MULLVAD-INTERFACE> -j MASQUERADE
sudo iptables -A FORWARD -i <MULLVAD-INTERFACE> -o <NETWORK-INTERFACE> -m state --state RELATED,ESTABLISHED -j ACCEPT
sudo iptables -A FORWARD -i <NETWORK-INTERFACE> -o <MULLVAD-INTERFACE> -j ACCEPT

# Save NAT rules
sudo apt install iptables-persistent -y
sudo netfilter-persistent save
```

### 4. Set Up the Web Interface

Install and configure the web panel for managing the gateway:

```bash
# Install Apache2, PHP, and required modules
sudo apt install git apache2 php libapache2-mod-php -y
git clone https://github.com/i-am-unbekannt/Mullvad-Gateway
cd Mullvad-Gateway

# Remove default web files and deploy the custom web panel
sudo rm -rf /var/www/html/*
sudo cp -r mullvad-gateway/* /var/www/html/
```

---

## Additional Information

- Ensure that your firewall and DHCP server are correctly configured to route traffic through the Mullvad Gateway.
- Use the web panel at http://MULLVAD-GATEWAY-IP/ to check the connection status of the VPN.
