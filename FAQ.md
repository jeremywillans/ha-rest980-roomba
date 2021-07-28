# ha-rest980-roomba FAQ

**Frequently Asked Questions**

I have compiled these questions from reviewing the HA Forum thread.. hopefully this should be a starting point if you encounter issues getting this running

Please let me know if you think anything else should be added to this list!

### 1. BLID/Password Issues

Retrieving your BLID/Password is fairly straight forward [this HA Add-on](https://github.com/jeremywillans/hass-addons/tree/master/roombapw) provides an easy way of obtaining your details.

You are essentially using roombapw to 'pair' as an additional device to your roomba - as outlines in the [official documentation](https://homesupport.irobot.com/app/answers/detail/a_id/8991/~/how-do-i-add-my-wi-fi-connected-robot-to-an-additional-mobile-device-or-phone%3F)

**Notes**
- Don't have the iRobot App running when doing this!
- Hold the Home button for two seconds when starting the pair process

### 2. Blank States URL

Make sure you **don't** have the HA Native Vacuum integration running - they don't play nicely together!!

### 3. Selective Cleaning isn't working

Make sure you have accurately copied your regions into the secrets.yaml file!
Check for trailing commands or brackets as these will **break** the code

```
vacuum_kitchen: '{"region_id": "16", "type": "rid"}'   <-- GOOD
vacuum_kitchen: '{"region_id": "16", "type": "rid",}'  <-- BAD - EXTRA COMMA
vacuum_kitchen: '{"region_id": "16", "type": "rid"}}'  <-- BAD - EXTRA BRACKET
```

If the regions all appear correct, then the next culprit is that the is a mis-match between the map_id values for rest980 and the iRobot App.

You can verify these using this process
* Start a selective clean from the iRobot App
* Navigate to ```http://<ip or fqdn of docker host>:<rest980port>/api/local/info/state```
* Look for the "lastCommand" section, and note the user_pmapv_id value
* Compare this value against the value HA is reporting (listed as an attribute on sensor.vacuum)

If the values don't match, try the following suggestions to bring them back in-sync.
* Update your map from the iRobot App (add a block zone, rename a room, etc.)
* Remove the Roomba from your iRobot App and re-add.

**Note** After perform these steps, verify that the iRobot Region ID values align with whats defined in HA! (Refer Step 5 for details)

### 4. PHP File downloads instead of rendering

HA / HA Core does not have in-built PHP Support. You need to deploy a php compatible web server to host this image.
This repo makes use of the php-nginx docker image (available as a HA Addon) to allow this file to render correctly.


### 5. The Check-Button-Card entries dont work

Make sure you have MQTT and [MQTT Discovery](https://www.home-assistant.io/docs/mqtt/discovery/) running in your HA Environmnent
If you use a different discovery prefix defined - such as "smartthings" - you will need to update each of the check-button-card entries in Lovelace.

You might need to click on them **twice** to reset the time to zero.

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

### 6. The Maintenance Icon on the Card does not reflect correctly

The Custom Roomba Card Maintenance icon (top right corner) uses the maint_due attribute of the sensor.rest980 for determining state.

- This attribute state is based on input_boolean.vacuum_main_due status which is dynamically controlled using the Vacuum Maintenance Check Automation.
- This automation periodically (15mins) runs a check of all sensor.vacuum_maint* entries and compares the current timestamp is greater or equal to the visiblity_timestamp (calculated from the visibility_timeout) for each entity.

### 7. The Map is not updating

The automation's for Roomba Location updating and Map Generation is expecting a Status of 'Clean', this is achieved in the vacuum.yaml file by  mapping the underlying vacuum cycle to reflect this, if you find that you status does not match this (for example the Roomba980 uses a mode of 'quick' which has since been incorporated), changes to the vacuum.yaml are needed to add this.

If you do encounter this - please let me know so I can update GH!

### 8. Map tips

There are several methods to create the map, however the below is a good getting started method

- Perform a full clean
- Delete the latest.png file in the vacuum directory (prevents image.php from displaying the cached file)
- Adjust the variables in the image.php file until the floor plan reflects your desired layout
-- Start with Height and Width, then Flip and X/Y Offsets, then Rotate and Scale
- Open map in browser and add last=true to end of URL (http://<fqdn/host>:<phpport>/image.php?last=true). This will create a latest.png and <date>.png file
- Copy this file to your local system and open in a paint program, such as [paint.net](https://www.getpaint.net/)
- Use this file as the "floor space" and use layers to either scale an existing floor plan or create a new one by creating "walls" around your floor space. 
- Upload floor.png file to vacuum directory, delete latest.png and open map again (without last=true!)
- Edit any further variables as required to achieve your desired layout.

### 9. Image Fill Mode

If you want to fill in the map rather than showing lines, you can update the variables ($line_thickness) in the image.php file to increase the thickness of lines.
To cover the 'overspray' caused in this mode, it is recommended to duplicate your floor plan image, making the floor transparent, adding to vacuum folder update the relevant variables in image.php ($overlay_walls and $walls_image)

### 10. Issues with MQTT Sensors

If your having issues with MQTT sensors, you can check the sensors directly on your MQTT Broker using [MQTT Explorer](http://mqtt-explorer.com/).
If in doubt, delete the sensor (with state and config topic) and recreate from Lovelace using the check-button-card.

### 11. Map Creation Process

The following steps outline the process used to generate the map

- You start cleaning
- Vacuum Clean Log automation executes. This calls the shell command vacuum_clear_log which writes over the vacuum.log file and then calls shell command vacuum_clear_image which in turn calls /image.php?clear=true which removes the latest.png file in the vacuum directory.
- Vacuum Update Location executes every 2 seconds
- When a location update is detected by Vacuum Log Position it writes this to the log file
- If Stuck, Vacuum Notify on Stuck Status executes and writes “Stuck” into the log file
- When finished, Vacuum Notify on Finished Cleaning executes and writes “Finished” to the log file
- 10 seconds after finished cleaning Vacuum Generate Image after Cleaning executes and calls the shell command vacuum_generate_image, which in turn calls /image.php?last=true which generates latest.png and .png files in the vacuum directory. you can test this manually to make sure its not a permissions issue.

Make sure all these automations are correctly firing and check your homeassistant log file for any potential errors being generated

### 12. Ordered Cleaning / Scheduled automation's

With the introduction of ordered cleaning, how you select rooms has been re-engineered to also work from this integration which is briefly explained as 

- Turn on Input Boolean (e.g. input_boolean.vacuum_clean_kitchen)
- "Vacuum Add Rooms for Cleaning" automation detects this and adds the text *AFTER* the vacuum_clean_ section (i.e. kitchen) and a comma to the end of input_text.vacuum_rooms
- Repeat for all rooms added (i.e. - kitchen,bathroom,bedroom)
- If you turn off an Input Boolean, via "Vacuum Remove Rooms for Cleaning" automation, this text is removed from input_text.vacuum_rooms
- When you have make the appropriate choices, then input_text.vacuum_rooms will contain the correctly ordered listing of rooms for cleaning.

The scheduled automation's simply define the ordered selections for this field (input_text.vacuum_rooms) and then call the "Vacuum Clean Rooms" automation.

### 13. Multi-Floor Configuration

I have included configurations for the Braava m6. This also includes a second yaml file which contains the extra items to implement multi-floor or multi-map cleaning and an included lovelace file with switching between "floors"

Multi-Floor is comprised of the following items

- A input_select is used to list and select the current map
- A new pmap attribute is added to the sensor.mop representing each of the maps
- A new pmap input_text is added to store the current "map", this is referenced in the clean rooms automation
- An automation is used to update the pmap and map input_texts when a new "map" is selected, and sets the default on HA start
- A separate image.php for each floor (individually named)
- New secrets representing each of the floor image files
  - New file append attribute has been added allowing simple identification of the image files
  - New braava icons and the model option has also been added
- New cameras are created for each of the "maps" (in addition to the original camera)
  - The original camera is used for sending notification, specifically for iOS where you can embed the image in the message
  - The individual cameras are included in the Lovelace card and get switched using state-switch when you select a different floor, along with the listing of selective cleaning rooms.
  - By using seperate cameras, you dont need to wait for the camera refresh interval to see the last clean of each respective floor.

In my example, there are 4 separate tiled rooms in my apartment, as such these are mapped out individually and treated as 4 separate "maps". I have left this format, allowing you to build on each of the selective room lists if you have mutiple floors, but several connected rooms in each.


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
