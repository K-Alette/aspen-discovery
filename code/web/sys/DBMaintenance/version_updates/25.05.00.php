<?php

function getUpdates25_05_00(): array {
	$curTime = time();
	return [
		/*'name' => [
			 'title' => '',
			 'description' => '',
			 'continueOnError' => false,
			 'sql' => [
				 ''
			 ]
		 ], //name*/

		//mark - Grove
		'system_variables_add_lida_github_repository' => [
			'title' => 'Add LIDA GitHub Repository to System Variables',
			'description' => 'Add a field to store the github repository for LiDA within System Variables to load release notes from',
			'continueOnError' => false,
			'sql' => [
				"ALTER TABLE system_variables add column lidaGitHubRepository VARCHAR(255) DEFAULT 'https://github.com/Aspen-Discovery/aspen-lida'",
			]
		], //system_variables_add_lida_github_repository
		'axis360_indexing_concurrency' => [
			'title' => 'Setup indexing concurrency for Boundless',
			'description' => 'Define numBoundlessSettingsToProcessInParallel in System variables',
			'sql' => [
				"ALTER TABLE system_variables add column numBoundlessSettingsToProcessInParallel int DEFAULT 1",
			]
		], //axis360_indexing_concurrency
		'axis360_setting_name' => [
			'title' => 'Add Boundless setting name',
			'description' => 'Add a name for Boundless settings',
			'sql' => [
				"ALTER TABLE axis360_settings ADD COLUMN name VARCHAR(100) DEFAULT ''",
				"UPDATE axis360_settings set name = concat('Setting ', id)",
			]
		], //axis360_setting_name

		//katherine - Grove

		//kirstien - Grove

		//kodi - Grove

		//Yanjun Li - ByWater
		'library_add_palace_project_library_id' => [
			'title' => 'library_add_palace_project_library_id',
			'description' => 'Add a field to store the palace project library id for the library',
			'continueOnError' => false,
			'sql' => [
				"ALTER TABLE library add column palaceProjectLibraryId VARCHAR(50) DEFAULT NULL",
			]
		], //library_add_palace_project_library_id

		// Leo Stoyanov - BWS
		'add_placard_image_max_height_to_themes' => [
			'title' => 'Add Placard Image Max Height to Themes',
			'description' => 'Adds a placardImageMaxHeight column to the themes table to control placard image height.',
			'sql' => [
				"ALTER TABLE themes ADD COLUMN IF NOT EXISTS `placardImageMaxHeight` INT DEFAULT 0",
			]
		], //add_placard_image_max_height_to_themes
		'custom_form_field_enums_to_text' => [
			'title' => 'Increase Custom Form Field EnumValues Size',
			'description' => 'Changes the enumValues column in web_builder_custom_form_field from VARCHAR(255) to TEXT to allow for longer select lists.',
			'sql' => [
				"ALTER TABLE web_builder_custom_form_field MODIFY COLUMN enumValues TEXT DEFAULT NULL",
			]
		], //custom_form_field_enums_to_text
		'ip_lookup_ipv6_support' => [
			'title' => 'Add Support for IPv6 Addresses',
			'description' => 'Add support for IPv6 addresses in ip_lookup table.',
			'continueOnError' => true,
			'sql' => [
				"ALTER TABLE ip_lookup MODIFY startIpVal VARCHAR(255) NULL COMMENT 'Numeric value for IPv4 or encoded string for IPv6'",
				"ALTER TABLE ip_lookup MODIFY endIpVal VARCHAR(255) NULL COMMENT 'Numeric value for IPv4 or encoded string for IPv6'"
			],
		], //ip_lookup_ipv6_support

		//alexander - Open Fifth
		'allow_filtering_of_linked_users_in_checkouts' => [
			'title' => 'Allow Filtering of Linked Users in Checkouts',
			'description' => 'Allow libraries the option of allowing users to filter their checkouts by linked user',
			'sql' => [
				'ALTER TABLE library ADD COLUMN allowFilteringOfLinkedAccountsInCheckouts TINYINT(1) DEFAULT 0',
			],
		], //allow_filtering_of_linked_users_in_checkouts
		'allow_selecting_checkouts_to_export' => [
			'title' => 'Allow Selecting Checkouts to Export',
			'description' => 'Allow libraries the option of allowing users to export only selected checkouts',
			'sql' => [
				'ALTER TABLE library ADD COLUMN allowSelectingCheckoutsToExport TINYINT(1) DEFAULT 0'
			],
		], //allow_selecting_checkouts_to_export
		'add_ability_to_highlight_campaigns_in_account_area' => [
			'title' => 'Add Ability to Highlight Campaigns In Account Area',
			'description' => 'Allow libraries to choose whether to display a block highlighting campaigns on the account page',
			'sql' => [
				"ALTER TABLE library ADD COLUMN highlightCommunityEngagement TINYINT(1) DEFAULT 0",
			],
		], //add_ability_to_highlight_campaigns_in_account_area
		'add_weight_to_campaign_milestones' => [
			'title' => 'Add Weight To Campaign Milestones',
			'description' => 'Add a weight column to campaign milestones to allow ordering',
			'sql' => [
				"ALTER TABLE ce_campaign_milestones ADD COLUMN weight int(11) NOT NULL DEFAULT 0",
			],
		], //add_weight_to_campaign_milestones

		//chloe - PTFS-Europe

		//James Staub - Nashville Public Library

		//Lucas Montoya - Theke Solutions
		'forceDebugLog' => [
			'title' => 'Enable Forced Logging of Debugging Information for Paypal, PayPalPayflow, Propay, InvoiceCloud, Square, Stripe, and ACISpeedPay Payments',
			'description' => 'Enable to show debugging information about Paypal payments',
			'sql' => [
				'ALTER TABLE paypal_settings ADD COLUMN forceDebugLog TINYINT(1) DEFAULT 0',
				'ALTER TABLE square_settings ADD COLUMN forceDebugLog TINYINT(1) DEFAULT 0',
				'ALTER TABLE stripe_settings ADD COLUMN forceDebugLog TINYINT(1) DEFAULT 0',
				'ALTER TABLE propay_settings ADD COLUMN forceDebugLog TINYINT(1) DEFAULT 0',
				'ALTER TABLE ncr_payments_settings ADD COLUMN forceDebugLog TINYINT(1) DEFAULT 0',
				'ALTER TABLE paypal_payflow_settings ADD COLUMN forceDebugLog TINYINT(1) DEFAULT 0',
				'ALTER TABLE aci_speedpay_settings ADD COLUMN forceDebugLog TINYINT(1) DEFAULT 0',
				'ALTER TABLE invoice_cloud_settings ADD COLUMN forceDebugLog TINYINT(1) DEFAULT 0',
			]
		], //enable_payments_debugging

		//other

	];
}