# ha-rest980-roomba FAQ

**Frequently Asked Questions**

I have compiled these questions from reviewing the HA Forum thread.. hopefully this should be a starting point if you encounter issues getting this running

Please let me know if you think anything else should be added to this list!

### 1. BLID/Password Issues

Retieving your BLID/Password is failry straight forward [this HA Add-on](https://github.com/jeremywillans/hass-addons/tree/master/roombapw) provides an easy way of obtaining your details.

You are essentially using roombapw to 'pair' as an additional device to your roomba - as outlines in the [official documentation](https://homesupport.irobot.com/app/answers/detail/a_id/8991/~/how-do-i-add-my-wi-fi-connected-robot-to-an-additional-mobile-device-or-phone%3F)

**Notes**
- Dont have the iRobot App running when doing this!
- Hold the Home button for two seconds when starting the pair process

### 2. Selective Cleaning isnt working

Make sure you have accurately copied your regions into the secrets.yaml file!
Check for trailing commands or brackets as these will **break** the code

```
vacuum_kitchen: '{"region_id": "16","region_name": "Kitchen","region_type": "kitchen"}'   <-- GOOD
vacuum_kitchen: '{"region_id": "16","region_name": "Kitchen","region_type": "kitchen"},'  <-- BAD - EXTRA COMMA
vacuum_kitchen: '{"region_id": "16","region_name": "Kitchen","region_type": "kitchen"}}'  <-- BAD - EXTRA BRACKET
```

### 3. PHP File downloads instead of rendering

HA / HA Core does not have inbuilt PHP Support. You need to deploy a php compatible web server to host this image.
This repo makes use of the php-nginx docker image (available as a HA Addon) to allow this file to render correctly.


### 4. The Check-Button-Card entries dont work

Make sure you have MQTT and [MQTT Discovery](https://www.home-assistant.io/docs/mqtt/discovery/) running in your HA Environmnent
If you use a different discovery prefix defined - such as "smartthings" - you will need to update each of the check-button-card entries in Lovelace.

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
  discovery_prefix: smartthings     <-- ADD THIS ENTRY
  visibility_timeout: 10 days
```

### 5. The Maintenance Icon on the Card does not reflect correctly

The Custom Roomba Card Maintnance icon (top right corner) uses the maint_due attribute of the sensor.rest980 for determinig state.

- This attribute state is based on input_boolean.vacuum_main_due status which is dynamically controlled using the Vacuum Maintenance Check Automation.
- This automation peridocially (15mins) runs a check of all sensor.vacuum_maint* entries and compares the current timestamp is greater or equal to the visiblity_timestamp (calculated from the visibility_timeout) for each entity.

**Note:** The oroginal chec-button-card does not have the visibility_timestamp value, you need to use my [forked version](https://github.com/jeremywillans/check-button-card) until the PR is merged.

### 6. The Map is not updating

The Automations for Roomba Location updating and Map Generation is expecting a Status of 'Clean', this is achieved in the vacuum.yaml file by  mapping the underlying vacuum cycle to reflect this, if you find that you status does not match this (for example the Roomba980 uses a mode of 'quick' which has since been incorporated), changes to the vacuum.yaml are needed to add this.

If you do encounter this - please let me know so I can update GH!

## Support

Got questions? Please post them [here][forum].

In case you've found a bug, please [open an issue on GitHub][issue].

[forum]: https://community.home-assistant.io/t/irobot-roomba-i7-configuration-using-rest980/161175
[issue]: https://github.com/jeremywillans/ha-rest9800-roomba/issues
[profile]: https://www.home-assistant.io/docs/authentication/#your-account-profile

## Disclaimer

This project is not affiliated, associated, authorized, endorsed by, or in any way officially connected with the iRobot Corporation,
or any of its subsidiaries or its affiliates. The official iRobot website can be found at https://www.irobot.com

## Credits

- [Facu ZAK](https://github.com/koalazak) for creating dorita980 and rest980 !
- [gotschi](https://community.home-assistant.io/u/gotschi/summary) for creating the original Roomba Map PHP file !
- [Ben Tomlin](https://github.com/benct) for creating the [xiaomi-vacuum-card](https://github.com/benct/lovelace-xiaomi-vacuum-card) from which my [roomba-vacuum-card](https://github.com/jeremywillans/lovelace-roomba-vacuum-card) is shamelessly derived from!

## My Repos

[ha-rest980-roomba](https://github.com/jeremywillans/ha-rest980-roomba) | 
[roomba-vacuum-card](https://github.com/jeremywillans/lovelace-roomba-vacuum-card) | 
[hass-addons](https://github.com/jeremywillans/hass-addons)

[![BMC](https://www.buymeacoffee.com/assets/img/custom_images/white_img.png)](https://www.buymeacoffee.com/jeremywillans)
