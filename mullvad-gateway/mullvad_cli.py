#!/usr/bin/python3
import sys, os, time, code
from colorama import Fore, Style, init
import subprocess
import re

init(autoreset=True)

def logo():
    print(Fore.LIGHTBLUE_EX+"""    
  __  __      _ _             _    ___      _                        
 |  \/  |_  _| | |_ ____ _ __| |  / __|__ _| |_ _____ __ ____ _ _  _ 
 | |\/| | || | | \ V / _` / _` | | (_ / _` |  _/ -_) V  V / _` | || |
 |_|  |_|\_,_|_|_|\_/\__,_\__,_|  \___\__,_|\__\___|\_/\_/\__,_|\_, |
                                                                |__/ 
 """+Fore.WHITE+"""------------------------ by @i_am_unbekannt ------------------------
 """+Fore.WHITE)

def get_mullvad_status():
    try:
        result = subprocess.run(
            ["mullvad", "status"], 
            stdout=subprocess.PIPE, 
            stderr=subprocess.PIPE, 
            text=True
        )
        
        if result.returncode != 0:
            raise Exception(f"Error: {result.stderr.strip()}")

        output = result.stdout.strip()
        return parse_mullvad_output(output)

    except Exception as e:
        print(f"Fehler: {e}")
        return None

def parse_mullvad_output(output):
    connected = "Connected" if "Connected" in output else "Disconnected"

    relay_match = re.search(r"Relay:\s+([a-zA-Z0-9-]+)", output)
    relay = relay_match.group(1) if relay_match else "Unknown"

    location_match = re.search(r"Visible location:\s+([^,]+), ([^\.]+)", output)
    location = f"{location_match.group(2)}, {location_match.group(1)}" if location_match else "Unknown"

    ip_match = re.search(r"IPv4:\s+([\d\.]+)", output)
    ip_address = ip_match.group(1) if ip_match else "Unknown"

    return {
        "Connected": connected,
        "Relay": relay,
        "Location": location,
        "IP Address": ip_address,
    }

def get_dns_servers():
    dns_servers = []
    try:
        with open("/etc/resolv.conf", "r") as resolv_conf:
            for line in resolv_conf:
                if line.startswith("nameserver"):
                    dns_servers.append(line.split()[1])
        return ", ".join(dns_servers)
    except Exception as e:
        return f"Error: {str(e)}"

def show_mullvad_status():
    status = get_mullvad_status()

    if status["Connected"] == "Connected":
        status_color = Fore.GREEN
    else:
        status_color = Fore.RED

    print(f"""Status: {status_color}{status['Connected']}{Style.RESET_ALL}
    Relay: {status['Relay']}
    Location: {status['Location']}
    IPv4: {status['IP Address']}
    DNS: {get_dns_servers()}
""")

def main():
    logo()
    show_mullvad_status()
main()
