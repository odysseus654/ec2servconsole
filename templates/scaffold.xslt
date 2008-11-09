<?xml version="1.0" ?>
<!--	scaffold.xslt
	Common user interface elements

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
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template match="loading">
		<div style="text-align: center; font: bold italic 25px sans-serif; color: gray" width="100%">
			Loading...&#160;&#160;
			<img src="images/spinner.gif" border="0" />
		</div>
	</xsl:template>

	<xsl:template match="reloading">
		<span style="text-align: center; font: bold italic 12px sans-serif; color: gray" width="100%">
			Loading...&#160;&#160;
			<img src="images/spinner.gif" border="0" />
		</span>
	</xsl:template>

	<xsl:template match="failed">
		<div style="text-align: center; font: bold 16px sans-serif; color: white; background-color: red" width="100%">
			Loading failed
		</div>
	</xsl:template>
</xsl:stylesheet>