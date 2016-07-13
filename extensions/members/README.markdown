# members

- Version: 1.2
- Author: Symphony Team
- Release Date: 6th December 2012
- Requirements: Symphony 2.3

Frontend membership extension for Symphony CMS. This version represents `1.2` which is the first version of the members extension to support Symphony 2.3.

## Installation and Setup

1.	Upload the 'members' folder to your Symphony 'extensions' folder.

2.	Enable it by selecting the "members", choose Enable from the with-selected menu, then click Apply.

3.	Create a new Section to hold your members entries, and add a member: Password, member: Role and either a member: Email or member: Username field. If you wish to email your members, you can add both.

4.	Go to System > Preferences and select your 'Active members Section'.

5.	Go to System > member Roles and setup your Roles as required. There is one default Role, Public that cannot be removed (but can be edited to suit your needs). This role represents an unauthenticated member.

6.	On the frontend, members can login using standard forms. Below is an example, where `{member: Username element_name}` should be substituted with your field handles, eg. `fields[username]`.

        <form method="post" autocomplete='off'>
            <label>Username
                <input name="fields[{member: Username element_name}]" type="text" />
            </label>
            <label>Password
                <input name="fields[{member: Password element_name}]" type="password" />
            </label>

            <input name="redirect" type="hidden" value="{$your-redirect-url}" />
            <input name="member-action[login]" type="submit" value="Login" />
        </form>

Event information will be returned in the XML similar to the following example:

		<events>
			<member-login-info logged-in="yes" id="72" />
		</events>

The `$member-id` and `$member-role` parameters will be added to the Page Parameters for you to use in your datasources to get information about the logged in member.

7.	You can log a member out using `<a href='?member-action=logout'>Logout</a>`

## Fields

This extension provides six additional fields:

- member: Username
- member: Email
- member: Password
- member: Role
- member: Activation
- member: Timezone


### member: Username

The member: Username field ensures all usernames are unique in the system. You can set a validator to ensure a username follows a particular pattern. member's can login by providing their username and password (see member: Password).

### member: Email

The member: Email field accepts only an email address and ensures that all email's are unique in the system. This field outputs an MD5 hash of the email address in the XML so that it be used as Gravatar hash. Like the member: Username field, members can login by providing their email address and password (see member: Password).

### member: Password

The member: Password field has a couple of additional settings to help improve the security of the member's password. Setting a password Salt can be done only once, and is used to provide some randomness when hashing the member's password. You can also set a minimum length required for a password and then there is three possible options for a minimum password strength, Weak, Good and Strong.

- Weak: This password is all lowercase, all uppercase, all numbers or all punctuation
- Good: The password must be a mixture of two of the following: lowercase, uppercase, numbers or punctuation
- Strong: The password must be a mixture of three or more of the following: lowercase, uppercase, numbers or punctuation

Passwords must be set with two fields, one to capture the password and one to confirm the password.

#### Events

- [members: Generate Recovery Code](https://github.com/symphonycms/members/wiki/members%3A-Generate-Recovery-Code)
- [members: Reset Password](https://github.com/symphonycms/members/wiki/members%3A-Reset-Password)

#### Filters

- members: Update Password


### member: Role

The member: Role field allows you to assign members with different Roles to allow them to access pages or execute particular events. The members extension installs with one default Role that cannot be deleted, Public. This Public Role is the default Role that all members of your website will fall under (ie. all unregistered members). This field allows you to set a default role, which the role that a member will take on when they register.

#### Filters

- members: Lock Role


### member: Activation

The member: Activation field enforces that all members who register to your site must first activate their account before they are treated as an authenticated member. This field allows you set a code expiry time, which is how long an activation code is valid for until it expires and a member will have to request a new one (see members: Regenerate Activation Code event) and an activation role. The activation role is given to a member when they register to your site, but haven't completed activation. Once they complete activation, they will be set to the default role as defined by the member: Role field.

#### Events

- [members: Activate Account](https://github.com/symphonycms/members/wiki/members%3A-Activate-Account)
- [members: Regenerate Activation Code](https://github.com/symphonycms/members/wiki/members%3A-Regenerate-Activation-Code)

#### Filters

- members: Lock Activation


### member: Timezone

The member: Timezone field allows members to have times displayed in their own timezone when on the site. It has one setting, Available Zones which allows you to set up what timezones, grouped by 'Zone',  are available for members to pick from.

## Events

This extension provides four additional events that can be added to your page:

- [members: Activate Account](https://github.com/symphonycms/members/wiki/members%3A-Activate-Account)
- [members: Regenerate Activation Code](https://github.com/symphonycms/members/wiki/members%3A-Regenerate-Activation-Code)
- [members: Generate Recovery Code](https://github.com/symphonycms/members/wiki/members%3A-Generate-Recovery-Code)
- [members: Reset Password](https://github.com/symphonycms/members/wiki/members%3A-Reset-Password)

Go to Blueprints > Components and click on the event name to view documentation for that event.

There are two global events that are available on any page your website:

### members: Login

	<form method="post" autocomplete='off'>
		<label>Username
			<input name="fields[{member: Username element_name}]" type="text" />
		</label>
		<label>Password
			<input name="fields[{member: Password element_name}]" type="password" />
		</label>

		<input name="redirect" type="hidden" value="{$your-redirect-url}" />
		<input name="member-action[login]" type="submit" value="Login" />
	</form>

### members: Logout

	<form method="post" autocomplete='off'>
		<input type='hidden' name='redirect' value='{$your-redirect-url}' />
		<input name="member-action[logout]" type="submit" value="Logout" />
	</form>

or

	<a href='?member-action=logout&redirect={$your-redirect-url}'>Logout</a>

### Create a member

You can create new member records using standard Symphony events on your active members section. The [wiki](https://github.com/symphonycms/members/wiki/members%3A-New) contains information about the response XML to expect from the fields provided by the members extension.

## Filters

This extension provides three event filters that you can add to your events to make them useful to members:

- members: Lock Activation
- members: Lock Role
- members: Update Password

### members: Lock Activation

The members: Lock Activation filter is to be attached to your own Registration event to force a member's activated state to be 'no' when a member is registering for your site. If the member already exists, using this filter on your event will ensure the member's activation value cannot be changed. This prevents any DOM hacking to make members activate themselves. If you do not use the member: Activation field, then you don't this filter on your Registration event.

### members: Lock Role

The members: Lock Role filter should be used as an additional security measure to ensure that the member cannot DOM hack their own Role. This filter ensures a newly registered member will always be of the Default Role or if updating a member record, the filter ensures the Role doesn't change from the member's current role. If you do not use the member: Role field, you don't need this filter on your Registration event. If you want to elevate a member between Roles, this can be done in the backend, or don't use this filter. Care will need to be taken that a member is not able to change their Role to whatever they please.

### members: Update Password

The members: Update Password filter is useful on Events where the member may update some of their profile information, and updating their password is optional. It essentially tells the extension that if the member hasn't provided their password, yet it's set to required, it's ok, just remember their current password details.

## Roles and Permissions

The members extension comes with a single default Role, Public. This role cannot be deleted, but it can be renamed and modified to suit your installation. This Role is assumed by any Frontend member who is not authenticated. Roles allow you to set Frontend event and page permissions.

## Email Templates

The [Email Template Filter](http://symphony-cms.com/download/extensions/view/20743/) or [Email Template Manager](http://symphony-cms.com/download/extensions/view/64322/) can be used to email information specific to the members extension such as member Registration, Password Reset and Activation Codes. These extensions allow Email Templates to be added as Event Filters to your events. Check the documentation for either extension to evaluate them for your situation. All bugs relating to those extensions should be reported to the respective extension, not the members extension.
