[![Logo Image](https://cdn.pterodactyl.io/logos/new/pterodactyl_logo.png)](https://pterodactyl.io)

![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/pterodactyl/panel/ci.yaml?label=Tests&style=for-the-badge&branch=1.0-develop)
![Discord](https://img.shields.io/discord/122900397965705216?label=Discord&logo=Discord&logoColor=white&style=for-the-badge)
![GitHub Releases](https://img.shields.io/github/downloads/pterodactyl/panel/latest/total?style=for-the-badge)
![GitHub contributors](https://img.shields.io/github/contributors/pterodactyl/panel?style=for-the-badge)

# Pterodactyl Panel

Pterodactyl® is a free, open-source game server management panel built with PHP, React, and Go. Designed with security
in mind, Pterodactyl runs all game servers in isolated Docker containers while exposing a beautiful and intuitive
UI to end users.

Stop settling for less. Make game servers a first class citizen on your platform.

![Image](https://cdn.pterodactyl.io/site-assets/pterodactyl_v1_demo.gif)

## Documentation

* [Panel Documentation](https://pterodactyl.io/panel/1.0/getting_started.html)
* [Wings Documentation](https://pterodactyl.io/wings/1.0/installing.html)
* [Community Guides](https://pterodactyl.io/community/about.html)
* Or, get additional help [via Discord](https://discord.gg/pterodactyl)

## Sponsors

I would like to extend my sincere thanks to the following sponsors for helping fund Pterodactyl's development.
[Interested in becoming a sponsor?](https://github.com/sponsors/matthewpi)

| Company                                                                           | About                                                                                                                                                                                                                                           |
|-----------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [**Aussie Server Hosts**](https://aussieserverhosts.com/)                         | No frills Australian Owned and operated High Performance Server hosting for some of the most demanding games serving Australia and New Zealand.                                                                                                 |
| [**BisectHosting**](https://www.bisecthosting.com/)                               | BisectHosting provides Minecraft, Valheim and other server hosting services with the highest reliability and lightning fast support since 2012.                                                                                                 |
| [**MineStrator**](https://minestrator.com/)                                       | Looking for the most highend French hosting company for your minecraft server? More than 24,000 members on our discord trust us. Give us a try!                                                                                                 |
| [**HostEZ**](https://hostez.io)                                                   | US & EU Rust & Minecraft Hosting. DDoS Protected bare metal, VPS and colocation with low latency, high uptime and maximum availability. EZ!                                                                                                     |
| [**Blueprint**](https://blueprint.zip/?utm_source=pterodactyl&utm_medium=sponsor) | Create and install Pterodactyl addons and themes with the growing Blueprint framework - the package-manager for Pterodactyl. Use multiple modifications at once without worrying about conflicts and make use of the large extension ecosystem. |
| [**indifferent broccoli**](https://indifferentbroccoli.com/)                      | indifferent broccoli is a game server hosting and rental company. With us, you get top-notch computer power for your gaming sessions. We destroy lag, latency, and complexity--letting you focus on the fun stuff.                              |

### Supported Games

Pterodactyl supports a wide variety of games by utilizing Docker containers to isolate each instance. This gives
you the power to run game servers without bloating machines with a host of additional dependencies.

Some of our core supported games include:

* Minecraft — including Paper, Sponge, Bungeecord, Waterfall, and more
  * **NEW: Minecraft Addon Manager** — Search, install, and manage mods from CurseForge and Modrinth, plugins from SpigotMC, and world management with import/export capabilities
* Rust
* Terraria
* Teamspeak
* Mumble
* Team Fortress 2
* Counter Strike: Global Offensive
* Garry's Mod
* ARK: Survival Evolved

In addition to our standard nest of supported games, our community is constantly pushing the limits of this software
and there are plenty more games available provided by the community. Some of these games include:

* Factorio
* San Andreas: MP
* Pocketmine MP
* Squad
* Xonotic
* Starmade
* Discord ATLBot, and most other Node.js/Python discord bots
* [and many more...](https://pterodactyleggs.com)

## Features

### Minecraft Addon Manager

Pterodactyl now includes a comprehensive **Minecraft Addon Manager** that provides:

#### 🔧 **Mod & Plugin Management**
- **Multi-Platform Search**: Search and install mods from CurseForge and Modrinth
- **Plugin Integration**: Browse and install plugins from CurseForge, Modrinth, and SpigotMC
- **Version Support**: Full support for Forge, Fabric, and Bukkit/Spigot/Paper platforms
- **Game Version Filtering**: Filter addons by specific Minecraft versions
- **One-Click Installation**: Direct installation to your server with automatic file placement

#### 🌍 **World Management**
- **World Creation**: Create new worlds with customizable settings
- **Import/Export**: Import worlds from ZIP files or export existing worlds
- **World Browser**: View all server worlds with size and modification info
- **Backup Integration**: Export worlds as ZIP files for easy backup and sharing

#### 📊 **Export & Analytics**
- **Addon Export**: Export your installed mods and plugins list as JSON or CSV
- **World Export**: Export individual worlds as ZIP archives
- **Usage Tracking**: Monitor addon usage and world statistics

#### 🎨 **Unified Interface**
- **Integrated UI**: Seamless integration with the existing Pterodactyl panel design
- **Tabbed Interface**: Easy navigation between search, installed addons, and world management
- **Real-time Updates**: Live updates when installing, uninstalling, or managing content
- **Responsive Design**: Works perfectly on desktop and mobile devices

#### ⚙️ **Configuration**
To enable CurseForge integration, add your API key to your environment configuration:
```bash
CURSEFORGE_API_KEY=your_curseforge_api_key_here
```

The addon manager automatically integrates with your existing Pterodactyl installation and supports all Docker-based Minecraft server configurations.

## License

Pterodactyl® Copyright © 2015 - 2022 Dane Everitt and contributors.

Code released under the [MIT License](./LICENSE.md).
