<?xml version="1.0" ?>
<!--	securitygroups.xslt
	User interface elements for the Security Groups panel

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
	xmlns:ec2="http://ec2.amazonaws.com/doc/2008-08-08/" version="1.0">
	<xsl:param name="sort" />
	<xsl:param name="sortdir" />
	<xsl:param name="action" />
	<xsl:param name="param" />

	<!-- Root element switch to get the "add rule" dialog to appear -->
	<xsl:template match="ec2:DescribeSecurityGroupsResponse">
		<xsl:choose>
			<xsl:when test="$action = 'addRule'">
				<xsl:call-template name="addRule" />
			</xsl:when>
			<xsl:otherwise>
				<xsl:apply-templates />
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
	<!-- Security group list - group list titles -->
	<xsl:template match="ec2:securityGroupInfo">
		<table class="securityGroups" width="80%">
			<tr class="group">
				<th>Name</th>
				<th>Description</th>
				<th></th>
			</tr>
			<xsl:apply-templates select="ec2:item" mode="securityGroupInfo" />
		</table>
	</xsl:template>

	<!-- Security group list - itemize groups -->
	<xsl:template match="ec2:item" mode="securityGroupInfo">
		<tr class="group">
			<td><b><xsl:value-of select="ec2:groupName" /></b></td>
			<td><xsl:value-of select="ec2:groupDescription" /></td>
			<td>
				<xsl:if test="ec2:groupName != 'default'">
					<a href="javascript:void(0)" onclick="appDelete(this,'{ec2:groupName} ({ec2:groupDescription})','{ec2:groupName}','deleteGroup');">
						Del
					</a>
				</xsl:if>
				<a href="javascript:void(0)" onclick="appPopupAction('addRule', null, '{ec2:groupName}');">
					Add Rule
				</a>
			</td>
		</tr>
		<tr>
			<td colspan="3">
				<xsl:apply-templates select="ec2:ipPermissions" />
			</td>
		</tr>
	</xsl:template>

	<!-- Security group list - rule list titles -->
	<xsl:template match="ec2:ipPermissions">
		<table class="securityRule" align="center" border="1" width="80%">
			<tr>
				<th>protocol</th>
				<th>from&#160;port</th>
				<th>to&#160;port</th>
				<th>source&#160;location</th>
				<th></th>
			</tr>
			<xsl:apply-templates select="ec2:item/ec2:groups|ec2:item/ec2:ipRanges" mode="ipPermissions" />
		</table>
	</xsl:template>

	<!-- Security group list - itemizing rules -->
	<xsl:template match="ec2:groups/ec2:item" mode="ipPermissions">
		<xsl:if test="../../ec2:ipProtocol = 'icmp'">
			<tr>
				<td colspan="3" align="center"><i>All network traffic</i></td>
				<td>
					<img src="images/silk/server.png" alt="Network" title="Network" />access group
					<b>
						<xsl:if test="ec2:item/ec2:userId != ../../ec2:ownerId">
							<xsl:value-of select="ec2:userId" />/
						</xsl:if>
						<xsl:value-of select="ec2:groupName" />
					</b>
				</td>
				<td>
					<a href="javascript:void(0)" onclick="appSubmitAction(this,'delIntRule','id={../../../../ec2:groupName}&amp;user={ec2:userId}&amp;group={ec2:groupName}')">
						Del
					</a>
				</td>
			</tr>
		</xsl:if>
	</xsl:template>

	<xsl:template match="ec2:ipRanges/ec2:item" mode="ipPermissions">
		<tr>
			<td><xsl:value-of select="../../ec2:ipProtocol" /></td>
			<xsl:choose>
				<xsl:when test="../../ec2:ipProtocol = 'icmp'">
					<td><i>n/a</i></td>
					<td><i>n/a</i></td>
				</xsl:when>
				<xsl:otherwise>
					<td><xsl:value-of select="../../ec2:fromPort" /></td>
					<td><xsl:value-of select="../../ec2:toPort" /></td>
				</xsl:otherwise>
			</xsl:choose>
			<td>
				<img src="images/silk/world.png" alt="Internet" title="Internet" />IP range
				<b><xsl:value-of select="ec2:cidrIp" /></b>
			</td>
			<td>
					<a href="javascript:void(0)" onclick="appSubmitAction(this,'delExtRule','id={../../../../ec2:groupName}&amp;proto={../../ec2:ipProtocol}&amp;ip={ec2:cidrIp}&amp;from={../../ec2:fromPort}&amp;to={../../ec2:toPort}')">
						Del
					</a>
			</td>
		</tr>
	</xsl:template>

	<!-- Form - add new group -->
	<xsl:template match="addGroup">
		<form onsubmit="appSubmitAction(this,'addGroup'); return false;">
			<table align="center">
				<tr>
					<td>Name</td>
					<td><input type="text" name="name" size="20" /></td>
				</tr>
				<tr>
					<td>Descr</td>
					<td><input type="text" name="descr" size="40" /></td>
				</tr>
				<tr>
					<td colspan="2" align="center">
						<input type="submit" value="Add Group" />
						<input type="button" value="Cancel" onclick="ModalWindow.activeWindow(this).destroy()" />
					</td>
				</tr>
			</table>
		</form>
	</xsl:template>

	<!-- Form - add new rule -->
	<xsl:template name="addRule">
		<table align="center">
			<tr>
				<td align="right">From</td>
				<td><input type="radio" name="type" id="secExternal" value="ext" checked="yes" onclick="secCheckRuleGrp()" /> an external IP, or</td>
			</tr>
			<tr>
				<td></td>
				<td><input type="radio" name="type" value="int" onclick="secCheckRuleGrp()" /> another security group</td>
			</tr>
			<tr><td colspan="2"><hr /></td></tr>
			<tr class="secExternal"><td colspan="2">
				<form onsubmit="appSubmitAction(this,'addExtRule'); return false;"><table align="center">
					<input type="hidden" name="id" value="{$param}" />
					<tr>
						<td align="right">Protocol:</td>
						<td><select name="proto">
							<option value="tcp" selected="yes">tcp</option>
							<option value="udp">udp</option>
							<option value="icmp">icmp</option>
						</select></td>
					</tr>
					<tr>
						<td>IP range:</td>
						<td><input type="text" name="ip" size="20" value="a.b.c.d/y" /></td>
					</tr>
					<tr>
						<td>Starting port:</td>
						<td><input type="text" name="from" size="10" value="0" /></td>
					</tr>
					<tr>
						<td>Ending port:</td>
						<td><input type="text" name="to" size="20" value="65535" /></td>
					</tr>
					<tr>
						<td colspan="2" align="center">
							<input type="submit" value="Add Rule" />
							<input type="button" value="Cancel" onclick="ModalWindow.activeWindow(this).destroy()" />
						</td>
					</tr>
				</table></form>
			</td></tr>
			<tr class="secInternal">
				<td align="right">From</td>
				<td><input type="radio" name="src" id="secIntMine" value="me" checked="yes" onclick="secCheckRuleGrp()" /> a group that I own, or</td>
			</tr>
			<tr class="secInternal">
				<td></td>
				<td><input type="radio" name="src" value="them" onclick="secCheckRuleGrp()" /> someone else's group</td>
			</tr>
			<tr class="secInternal"><td colspan="2"><hr /></td></tr>
			<tr class="secInternalTheirs"><td colspan="2">
				<form onsubmit="appSubmitAction(this,'addIntRule'); return false;"><table align="center">
					<input type="hidden" name="id" value="{$param}" />
					<tr>
						<td>Source group owner ID:</td>
						<td><input type="text" name="user" size="15" value="1234567890" /></td>
					</tr>
					<tr>
						<td>Source group:</td>
						<td><input type="text" name="group" size="10" value="groupName" /></td>
					</tr>
					<tr>
						<td colspan="2" align="center">
							<input type="submit" value="Add Rule" />
							<input type="button" value="Cancel" onclick="ModalWindow.activeWindow(this).destroy()" />
						</td>
					</tr>
				</table></form>
			</td></tr>
			<tr class="secInternalMine"><td colspan="2">
				<form onsubmit="appSubmitAction(this,'addIntRule'); return false;"><table align="center">
					<input type="hidden" name="id" value="{$param}" />
					<tr>
						<td>Source group:</td>
						<td>
							<select name="group">
								<xsl:for-each select="/ec2:DescribeSecurityGroupsResponse/ec2:securityGroupInfo/ec2:item" >
									<option value="{ec2:groupName}">
										<xsl:value-of select="ec2:groupName" /> (<xsl:value-of select="ec2:groupDescription" />)
									</option>
								</xsl:for-each>
							</select>
							<input type="hidden" name="user" value="{/ec2:DescribeSecurityGroupsResponse/ec2:securityGroupInfo/ec2:item/ec2:ownerId}" />
						</td>
					</tr>
					<tr>
						<td></td>
					</tr>
					<tr>
						<td colspan="2" align="center">
							<input type="submit" value="Add Rule" />
							<input type="button" value="Cancel" onclick="ModalWindow.activeWindow(this).destroy()" />
						</td>
					</tr>
				</table></form>
			</td></tr>
		</table>
	</xsl:template>
</xsl:stylesheet>
