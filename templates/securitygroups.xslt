<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:ec2="http://ec2.amazonaws.com/doc/2007-01-03/" version="1.0">
	<xsl:param name="sort" />
	<xsl:param name="sortdir" />
	<xsl:param name="action" />

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
	
	<xsl:template match="ec2:securityGroupInfo">
		<table border="1" width="80%">
			<tr>
				<th>Name</th>
				<th>Description</th>
				<th></th>
			</tr>
			<xsl:apply-templates select="ec2:item" mode="securityGroupInfo" />
		</table>
	</xsl:template>

	<xsl:template match="ec2:item" mode="securityGroupInfo">
		<tr class="securityGroup">
			<td><b><xsl:value-of select="ec2:groupName" /></b></td>
			<td><xsl:value-of select="ec2:groupDescription" /></td>
			<td>
				<xsl:if test="ec2:groupName != 'default'">
					<a href="javascript:void(0)" onclick="appDelete(this,'{ec2:groupName} ({ec2:groupDescription})','{ec2:groupName}','deleteGroup');">
						Del
					</a>
				</xsl:if>
				<a href="javascript:void(0)" onclick="appPopupAction('addRule');">
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

	<xsl:template match="ec2:ipPermissions">
		<table align="center" border="1" width="80%">
			<tr>
				<th>protocol</th>
				<th>from&#160;port</th>
				<th>to&#160;port</th>
				<th>source&#160;location</th>
			</tr>
			<xsl:apply-templates select="ec2:item" mode="ipPermissions" />
		</table>
	</xsl:template>

	<xsl:template match="ec2:item" mode="ipPermissions">
		<tr>
			<td><xsl:value-of select="ec2:ipProtocol" /></td>
			<xsl:choose>
				<xsl:when test="ec2:ipProtocol = 'icmp'">
					<td><i>n/a</i></td>
					<td><i>n/a</i></td>
				</xsl:when>
				<xsl:otherwise>
					<td><xsl:value-of select="ec2:fromPort" /></td>
					<td><xsl:value-of select="ec2:toPort" /></td>
				</xsl:otherwise>
			</xsl:choose>
			<td>
				<xsl:if test="ec2:ipRanges != ''">
					<img src="images/silk/world.png" alt="Internet" title="Internet" />IP range
					<b><xsl:value-of select="ec2:ipRanges" /></b>
				</xsl:if>
				<xsl:if test="ec2:groups != ''">
					<img src="images/silk/server.png" alt="Network" title="Network" />access group
					<b>
						<xsl:if test="ec2:groups/ec2:item/ec2:userId != ../ec2:ownerId">
							<xsl:value-of select="ec2:groups/ec2:item/ec2:userId" />/
						</xsl:if>
						<xsl:value-of select="ec2:groups/ec2:item/ec2:groupName" />
					</b>
				</xsl:if>
			</td>
		</tr>
	</xsl:template>

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

	<xsl:template name="addRule">
		<table align="center">
			<tr>
				<td align="right">From</td>
				<td><input type="radio" name="type" value="ext" checked="yes" /> an external IP, or</td>
			</tr>
			<tr>
				<td></td>
				<td><input type="radio" name="type" value="int" /> another security group</td>
			</tr>
			<tr><td colspan="2"><hr /></td></tr>
			<tr><td colspan="2">
				<form onsubmit="appSubmitAction(this,'addExtRule'); return false;"><table align="center">
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
						<td><input type="text" name="ip" size="20" value="65535" /></td>
					</tr>
					<tr>
						<td colspan="2" align="center">
							<input type="submit" value="Add Rule" />
							<input type="button" value="Cancel" onclick="ModalWindow.activeWindow(this).destroy()" />
						</td>
					</tr>
				</table></form>
			</td></tr>
			<tr>
				<td align="right">From</td>
				<td><input type="radio" name="src" value="me" checked="yes" /> a group that I own, or</td>
			</tr>
			<tr>
				<td></td>
				<td><input type="radio" name="src" value="them" /> someone else's group</td>
			</tr>
			<tr><td colspan="2"><hr /></td></tr>
			<tr><td colspan="2">
				<form onsubmit="appSubmitAction(this,'addIntRule'); return false;"><table align="center">
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
			<tr><td colspan="2">
				<form onsubmit="appSubmitAction(this,'addIntRule'); return false;"><table align="center">
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