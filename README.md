# ha-rest980-roomba

Home Assistant - iRobot Roomba i7+ Configuration using rest980

This repository provides configuration to get an iRobot Roomba i7+ robot vacuum cleaner integrated with Home Assistant using the rest980 Docker Image!

[![gh_release]](../../releases)
[![gh_last_commit]](../../commits/master)
![Supports amd64 Architecture][amd64-shield]
![Supports armv7 Architecture][armv7-shield]

## Example Lovelace UI View

![Lovelace Example](lovelace_example.png)

## Setup Instructions

### Step 1: Prerequesites

Before completing any of the below steps, you need to first configure your rooms/zones using the iRobot App!

The following custom components are used in this deployment - these can be installed from HACS
- [roomba-vacuum-card] - Custom Plugin Repository
- [card-mod](https://github.com/thomasloven/lovelace-card-mod)
- [check-button-card](https://github.com/custom-cards/check-button-card)
- [lovelace-fold-entity-row](https://github.com/thomasloven/lovelace-fold-entity-row)
- [text-divider-row](https://github.com/custom-cards/text-divider-row/)
- [button-card](https://github.com/custom-cards/button-card)

A working MQTT Server with discovery is also needed (in conjunction with the check-button-card for Maintenance items) <https://www.home-assistant.io/integrations/sensor.mqtt/>

### Step 2: Get Robot Login Details

**NOTE** Do not have iRobot App running on your phone when doing this !!!

**DOCKER**
```
docker run -it node sh -c "npm install -g dorita980 && get-roomba-password <robotIP>"
```
**HA ADDON**

- Add the following Github Repository to your HA Add-on Store
  https://github.com/jeremywillans/hass-addons
- Locate and install the roombapw Add-on, following the included instructions.

**OTHER**
If you dont have direct access to Docker you can clone and install the dorita980 package locally (requires git and node to be installed) - refer [here](https://github.com/koalazak/dorita980#how-to-get-your-usernameblid-and-password) for instructions

### Step 3: Configure Vacuum Map Directory
To allow the map to be correctly produced, you will need to create a new vacuum directory. I have chosen to put this inside the HA configuration directory, but you can choose to put this elsewhere and update the configuration accordingly (if you are using HASS and referencing my hass-addons repo, please leave this at the default!)

Copy the contents of the Vacuum directory from Github into this folder.

**Note:** The image.php file will need updating, but this will be done after the setup is complete.

### Step 4: Configure Docker / HA Add-on
I use docker compose for all my HA related images, but have also listed the docker run command (copied from the rest980 github page)
I have also included an example PHP Docker Image which i use to host the map.

To allow this to work on Hass.io - I have created a custom github repository which can be added to Hass.io allowing the installation of the rest980 and nginx-php Docker Images (support arm and amd64 platforms)

**Note:** Docker Hub only hosts a amd64 version of rest980, I have configured the HA Addon (formerley HASS) to build the image locally so it works on RPi (armv7). If you dont use HA and want to run rest980 on a non-arm64 platform, you will need to build the image manually.

**DOCKER-COMPOSE**
> [docker-compose.yaml](https://github.com/jeremywillans/ha-rest980-roomba/blob/master/docker-compose.yaml)

**Note:** I use a separate docker bridged network which can be created with:
```
docker network create docker
```
**DOCKER RUN**
```
docker run -name rest980 -e BLID=myuser -e PASSWORD=mypass -e ROBOT_IP=myrobotIP -e FIRMWARE_VERSION=2 -p 3000:3000 koalazak/rest980:latest
docker run -name php-nginx -p 3001:8080 -v /<HA_CONFIG>/roomba:/app -e NGINX_WEBROOT=/app webhippie/php-nginx:latest
```
**PORTAINER-IN-HASSIO**
> [docker-portainer-stack.yaml](https://github.com/jeremywillans/ha-rest980-roomba/blob/master/docker-portainer-stack.yaml)

Confirm you can access the WebUI
```
http://<ip or fqdn of docker host>:<port>/api/local/info/state
```
**HA ADDON**

- Add the following Github Repository to your HA Add-on Store
  https://github.com/jeremywillans/hass-addons
- Install the rest980 addon, then update and save the configuration options 
- Install and configure the php-nginx addon.
- Start rest980 and php-nginx

**Note** There are two copies of each addon listed, these allow you to run a second robot (or bravva), you do not need these if you only have 1 unit!

**Alternative**: You can also run these locally by creating a rest980 (or similar) folder within "addons/local" and then copying the contents from each folder on my [hass-addons](https://github.com/jeremywillans/hass-addons/) repository. 

This will create a new local addon which you can install

### Step 5: Get Room Details

* Initiate a Clean Rooms clean from the iRobot App, ensuring you note the order in which you select the rooms (room names are no longer shown in the api)
* Navigate to ```http://<ip or fqdn of docker host>:<rest980port>/api/local/info/state```
* Look for the "lastCommand" section and copy down the following info, noting the order as this references what you selected in the app.
    - regions (type "rid" is Room, type "zid" is Zone)

**Note:** If you recreate your map, you will need to repeat this process as the region_id's will most likely change!

### Step 6: Configure Home Assistant Package and Secrets

The below are my configuration YAML files which uses the [Packages](https://www.home-assistant.io/docs/configuration/packages/) feature in HA to keep all the separate components together.

I split off the regions into the secrets file to make it easier to manage for future updates (these will change if you update your floorplan from the iRobot app)

I have tried to map as many of the reported statuses, however I occasionally get an "Unknown" in the logs, if you work out another state, please post it up!

I have included notification options (with optional inclusion of the map for IOS users), these can be deleted from vacuum.yaml if not needed

**Notes:** 
- Make sure you **remove** any trailing commas from the regions when copying them into the secrets file!
- The input_booleans and input_text entries all start with vacuum as this is used in the templates for correct mapping in lovelace

> [secrets.yaml](https://github.com/jeremywillans/ha-rest980-roomba/blob/master/secrets.yaml)
> [vacuum.yaml](https://github.com/jeremywillans/ha-rest980-roomba/blob/master/vacuum.yaml)

You will **NEED** to update the following items to match the rooms you have used
- Secret, Input Boolean and Input Text per room/zone
- Group "Vacuum_Rooms"
- Automation "Vacuum Add Rooms for Cleaning" Ttrigger entities
- Automation "Vacuum Remove Rooms for Cleaning" trigger entities

### Step 7: Configure Map Options

You will need to update the variables at the top of the image.php to align with your environment.

Specifically the log, rest980, token and timezone ones should be done now - the rest are best to update once a full clean has run (to populate the map)

You can create the long-lived HA Token from your [HA Account Profile][profile] page.

### Step 8: Configure Lovelace

I have used the below lovelace configuration, ensure the relevant custom components are installed, as listed in the prerequesites section

Note: This config is shown as the two cards used
- Vertical Stack
- Picture Glance

> [lovelace.yaml](https://github.com/jeremywillans/ha-rest980-roomba/blob/master/lovelace.yaml)

You will **NEED** to update the following items to match the rooms you have used
- Input Boolean per room/zone

### Step 9: Update Map Options

After you have run a clean cycle, the map should be populating however it is likely not quite sized correctly.

In the Vacuum directory (in HA Configuration), you will need to update the height, width, offset and flip options in the image.php file to correctly reflect your layout.

I have moved these as variables at the top of the file making it easier to update.

You will also need to replace the included floor.png file with an floor plan or similar file which is used as the background for the robot map.

**Note:** Once the vacuum has completed is clean, the image.php file references the latest.png file in the local vacuum directory so your changes wont be reflected upon refresh.
Simply delete the "latest.png" file in the vacuum directory to force map regeneration each time (or run http://<ip or fqdn of docker host>:<nginxphpport>/image.phpclear=true```)

### Step 10: Create Vacuum Maintenace Sensors

You will need to create the Maintenance Sensors - simply expand the Maintenance dropdown in Lovelace and click the green check next to each item to create these. You might need to click on them **twice** to reset the time to zero.

**Note:** check-button-card is assuming you have [MQTT Discovery](https://www.home-assistant.io/docs/mqtt/discovery/) enabled using the default discovery prefix of "homeassistant"
If you have a different discovery prefix defined - such as "smartthings" - please add the following to each of the Maintenance Tasks in Lovelace

```
- entity: sensor.vacuum_maint_clean_brushes
  severity:
   - hue: '140'
     value: 8 days
   - hue: '55'
     value: 10 days
   - hue: '345'
     value: 14 days
  title: Brushes
  type: 'custom:check-button-card'
  discovery_prefix: smartthings <--------------- THIS
  visibility_timeout: 10 days
```

### Step 12: Update Recorder

To prevent the database from storing unrequired data, the below privides an example of suggested exclusions you can add to your [recorder](https://www.home-assistant.io/integrations/recorder/#common-filtering-examples) component within your configuration.yaml file

```
recorder:
  <existing code here, if any>
  exclude:
    entity_globs:
      - sensor.vacuum_*
      - automation.vacuum_*
    entities:
      - sensor.rest980
      - camera.roomba
```

### Step 11: Enjoy!

## Support

Check the FAQ [here][faq]!

Got questions? Please post them [here][forum].

In case you've found a bug, please [open an issue on GitHub](../../issues).

## Disclaimer

This project is not affiliated, associated, authorized, endorsed by, or in any way officially connected with the iRobot Corporation,
or any of its subsidiaries or its affiliates. The official iRobot website can be found at https://www.irobot.com

## Credits

- [Facu ZAK][facuzak] for creating dorita980 and rest980 !
- [gotschi] for creating the original Roomba Map PHP file !
- [Ben Tomlin][benct] for creating the [xiaomi-vacuum-card] from which my [roomba-vacuum-card] is shamelessly derived from!

## My Repos

[ha-rest980-roomba] | 
[roomba-vacuum-card] | 
[hass-addons] | 
[event-emitter]

[![BMC]](https://www.buymeacoffee.com/jeremywillans)


[gh_release]: https://img.shields.io/github/v/release/jeremywillans/ha-rest980-roomba.svg?style=for-the-badge
[gh_last_commit]: https://img.shields.io/github/last-commit/jeremywillans/ha-rest980-roomba.svg?style=for-the-badge
[amd64-shield]: https://img.shields.io/badge/amd64-yes-green.svg?style=for-the-badge
[armv7-shield]: https://img.shields.io/badge/armv7-yes-green.svg?style=for-the-badge

[forum]: https://community.home-assistant.io/t/irobot-roomba-i7-configuration-using-rest980/161175
[issue]: https://github.com/jeremywillans/ha-rest980-roomba/issues
[faq]: https://github.com/jeremywillans/ha-rest980-roomba/blob/master/FAQ.md
[profile]: https://www.home-assistant.io/docs/authentication/#your-account-profile

[facuzak]: https://github.com/koalazak
[rest980]: https://github.com/koalazak/rest980
[dorita980]: https://github.com/koalazak/rest980
[gotschi]: https://community.home-assistant.io/u/gotschi/summary
[benct]: https://github.com/benct
[xiaomi-vacuum-card]: https://github.com/benct/lovelace-xiaomi-vacuum-card

[ha-rest980-roomba]: https://github.com/jeremywillans/ha-rest980-roomba
[roomba-vacuum-card]: https://github.com/jeremywillans/lovelace-roomba-vacuum-card
[hass-addons]: https://github.com/jeremywillans/hass-addons
[event-emitter]: https://github.com/jeremywillans/event-emitter
[BMC]: https://www.buymeacoffee.com/assets/img/custom_images/white_img.png

[forum]: https://community.home-assistant.io/t/irobot-roomba-i7-configuration-using-rest980/161175
