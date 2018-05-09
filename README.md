GLPI ProjectBridge Plugin
=========================
By Probesys: https://probesys.com
Language: french
Works with: GLPI 9.2.1 and 9.2.3 (9.2.2 untested)

This plugin allows to count down time from contracts by linking tickets with project tasks and project tasks with contracts.

Features:

* configure recipients of expiration alerts and reached quota alerts
* link default contracts to entities: tickets created in that entity will automatically be linked to the project and thus to the contract
* link contracts to projects
* automatically create a project and it's task when creating a contract
* renew a contract when quota is reached or it expired
* create projects from existing contracts and link them: `php plugins/projectbridge/scripts/link_data.php`
* change a ticket's link to another project, and thus another contract

Known issues:

* when there is no contract start date or a wrongly formatted one, the project's and task's start date are timestamp zero (Jan 1st 1970), which also messes up renewal
* link_data.php script does not link all existing contracts with projects, a manual check is required
* alerts are still sent even if notifications are disabled

Possible evolutions:

* add a way to link all concerned tickets to their project tasks in link_data.php
* use GLPI notifications to send the contract alerts
