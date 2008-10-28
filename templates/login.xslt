<?xml version="1.0" ?>
<!--	login.xslt
	User interface elements for the login dialog and account panels

	Part of EC2 Server Console http://sourceforge.net/ec2servconsole

	Copyright 2007-2008 Erik Anderson

	Licensed under the Apache License, Version 2.0 (the "License");
	you may not use this file except in compliance with the License.
	You may obtain a copy of the License at

	http://www.apache.org/licenses/LICENSE-2.0

	Unless required by applicable law or agreed to in writing, software
	distributed under the License is distributed on an "AS IS" BASIS,
	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	See the License for the specific language governing permissions and
	limitations under the License.
-->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:ec2="http://ec2.amazonaws.com/doc/2007-01-03/" version="1.0">

	<!-- login dialog -->
	<xsl:template match="loginDialog">
		<form onsubmit="appSubmitAction(this,'login'); return false;">
			<table align="center">
				<tr><td colspan="2">
					You need to login to complete this action<br />
				</td></tr>
				<tr>
					<td>Login</td>
					<td><input type="text" name="name" size="20" /></td>
				</tr>
				<tr>
					<td>Password</td>
					<td><input type="password" name="pass" size="20" /></td>
				</tr>
				<tr>
					<td colspan="2" align="center">
						<input type="submit" value="Login" />
						<input type="button" value="Cancel" onclick="ModalWindow.activeWindow(this).destroy()" />
					</td>
				</tr>
				<tr>
					<td colspan="2" style="font-size: 8pt; font-style: italic">
						<br />
						Have an EC2 access code but need a login here?
						<a href="javascript:void(0)" onclick="ModalWindow.activeWindow(this).destroy(); appSetPanel('login', 'createAccount')">Click here</a>
					</td>
				</tr>
			</table>
		</form>
	</xsl:template>
	
	<!-- account creation -->
	<xsl:template match="createAccount">
		<form onsubmit="appSubmitAction(this,'createAccount'); return false;">
			<i>Use this form to create a <b>new organization</b>, or when you are a new user that owns
			an Amazon EC2 account.  If you are attempting to add a login to an existing organization,
			then <b>this is not the place for you</b>, please ask the account owner or someone granted
			suitable permissions to create a login for you here.</i>
			
			<table>
				<tr>
					<td align="right">New login:</td>
					<td><input type="text" name="login" size="20" /></td>
				</tr>
				<tr>
					<td align="right">Full name:</td>
					<td><input type="text" name="name" size="50" /></td>
				</tr>
				<tr>
					<td align="right">Email:</td>
					<td><input type="text" name="email" size="50" /></td>
				</tr>
				<tr>
					<td align="right">Password:</td>
					<td><input type="password" name="password" id="pass1" size="20" /></td>
				</tr>
				<tr>
					<td align="right">Password again:</td>
					<td><input type="password" id="pass2" size="20" /></td>
				</tr>
				<tr>
					<td colspan="2"><hr /></td>
				</tr>
				<tr>
					<td align="right">Organization/<br/>Group Name:</td>
					<td><input type="text" name="organization" size="50" /></td>
				</tr>
				<tr>
					<td align="right">EC2 Access Key ID:</td>
					<td><input type="text" name="ec2account" size="50" /></td>
				</tr>
				<tr>
					<td align="right">EC2 Secret Access Key:</td>
					<td><input type="password" name="ec2pass" size="50" /></td>
				</tr>
				<tr>
					<td colspan="2" align="center">
						<input type="submit" value="Create Account" />
					</td>
				</tr>
			</table>
		</form>
	</xsl:template>
</xsl:stylesheet>
