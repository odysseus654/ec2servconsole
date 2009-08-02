<?xml version="1.0" ?>
<!--	images.xslt
	User interface elements for the Images panel

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
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:ds="http://ec2servconsole.sourceforge.net/2009/DataStore" version="1.0">
	<xsl:param name="sort" />
	<xsl:param name="sortdir" />
	<xsl:param name="pageno" select="'1'" />
	<xsl:param name="rowsperpage" select="'100'" />
	<xsl:variable name="minpos" select="$rowsperpage * ($pageno - 1)" />
	<xsl:variable name="maxpos" select="$minpos + $rowsperpage - 1" />
	
	<xsl:template name="colSortHdr">
		<xsl:param name="col" />
		<xsl:param name="title" select="$col" />
		<xsl:variable name="newdir">
			<xsl:choose>
				<xsl:when test="$sort=$col and $sortdir='d'">a</xsl:when>
				<xsl:otherwise>d</xsl:otherwise>
			</xsl:choose>
		</xsl:variable>
		
		<th onclick="panelReplay(this, {{sort:'{$col}', sortdir:'{$newdir}'}})" title="Sort by this field" style="cursor: pointer">
			<xsl:if test="$sort = $col">
				<xsl:choose>
					<xsl:when test="$sortdir='d'">
						<xsl:attribute name="class">sortdn</xsl:attribute>
						<img src="images/silk/bullet_arrow_down.png" />
					</xsl:when>
					<xsl:otherwise>
						<xsl:attribute name="class">sortup</xsl:attribute>
						<img src="images/silk/bullet_arrow_up.png" />
					</xsl:otherwise>
				</xsl:choose>
			</xsl:if>
			<xsl:value-of select="$title" />
		</th>
	</xsl:template>
	
	<xsl:template name="paging">
		<xsl:param name="numitems" />
		<xsl:variable name="numpages" select="floor((($numitems - 1) div $rowsperpage) + 1)" />
		<xsl:if test="$numpages &gt; 1">
			<div style="text-align:center">
				<xsl:if test="$pageno &gt; 2">
					<img src="images/silk/resultset_first.png" alt="First" style="cursor:pointer" onclick="panelReplay(this, {{pageno:1}})" />
					<span style="width:100">&#160;</span>
				</xsl:if>
				<xsl:if test="$pageno &gt; 1">
					<img src="images/silk/resultset_previous.png" alt="Previous" style="cursor:pointer" onclick="panelReplay(this, {{pageno:{$pageno - 1}}})" />
					<span style="width:100">&#160;</span>
				</xsl:if>
				Page <xsl:value-of select="$pageno" /> of <xsl:value-of select="$numpages" />
				(<xsl:value-of select="$numitems" /> items total)
				<xsl:if test="$pageno &lt; $numpages">
					<span style="width:100">&#160;</span>
					<img src="images/silk/resultset_next.png" alt="Next" style="cursor:pointer" onclick="panelReplay(this, {{pageno:{$pageno + 1}}})" />
				</xsl:if>
				<xsl:if test="$pageno &lt; $numpages - 1">
					<span style="width:100">&#160;</span>
					<img src="images/silk/resultset_last.png" alt="Last" style="cursor:pointer" onclick="panelReplay(this, {{pageno:{$numpages}}})" />
				</xsl:if>
			</div>
		</xsl:if>
	</xsl:template>
	
	<xsl:template match="ds:ListImagesResponse">
		<xsl:choose>
			<xsl:when test="count(ds:image) = 1">
				<xsl:apply-templates select="ds:image" mode="singleItem" />
			</xsl:when>
			<xsl:otherwise>
				<xsl:call-template name="paging">
					<xsl:with-param name="numitems" select="count(ds:image)" />
				</xsl:call-template>

				<table border="1" align="center" width="98%">
					<tr>
						<xsl:call-template name="colSortHdr">
							<xsl:with-param name="col" select="'id'" />
							<xsl:with-param name="title" select="'ID'" />
						</xsl:call-template>
						<xsl:call-template name="colSortHdr">
							<xsl:with-param name="col" select="'loc'" />
							<xsl:with-param name="title" select="'Location'" />
						</xsl:call-template>
						<xsl:call-template name="colSortHdr">
							<xsl:with-param name="col" select="'arch'" />
							<xsl:with-param name="title" select="'Arch'" />
						</xsl:call-template>
						<th></th>
					</tr>
					
					<xsl:variable name="sortorder">
						<xsl:choose>
							<xsl:when test="$sortdir='d'">descending</xsl:when>
							<xsl:otherwise>ascending</xsl:otherwise>
						</xsl:choose>
					</xsl:variable>
					<xsl:choose>
						<xsl:when test="$sort = 'id'">
							<xsl:apply-templates select="ds:image" mode="imagesSet">
								<xsl:sort select="concat(ds:label,ds:imageId)" order="{$sortorder}" />
							</xsl:apply-templates>
						</xsl:when>
						<xsl:when test="$sort = 'loc'">
							<xsl:apply-templates select="ds:image" mode="imagesSet">
								<xsl:sort select="concat(ds:descr,ds:imageLocation)" order="{$sortorder}" />
							</xsl:apply-templates>
						</xsl:when>
						<xsl:when test="$sort = 'arch'">
							<xsl:apply-templates select="ds:image" mode="imagesSet">
								<xsl:sort select="ds:platform" order="{$sortorder}" />
								<xsl:sort select="ds:architecture" order="{$sortorder}" />
							</xsl:apply-templates>
						</xsl:when>
						<xsl:otherwise>
							<xsl:apply-templates select="ds:image" mode="imagesSet" >
								<xsl:sort select="ds:imageId" order="descending"/>
							</xsl:apply-templates>
						</xsl:otherwise>
					</xsl:choose>
				</table>

				<xsl:call-template name="paging">
					<xsl:with-param name="numitems" select="count(ds:image)" />
				</xsl:call-template>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
	<xsl:template match="ds:image" mode="imagesSet">
		<xsl:if test="position() &gt;= $minpos and position() &lt;= $maxpos">
			<tr>
				<xsl:choose>
					<xsl:when test="ds:isInvalid">
						<xsl:attribute name="class">disabled</xsl:attribute>
					</xsl:when>
					<xsl:when test="not(ds:isPublic)">
						<xsl:attribute name="class">private</xsl:attribute>
					</xsl:when>
					<xsl:when test="ds:isPaid">
						<xsl:attribute name="class">paid</xsl:attribute>
					</xsl:when>
				</xsl:choose>
				<td style="wrap: nowrap">
					<img src="images/silk/cd.png" alt="machine" />
					<xsl:choose>
						<xsl:when test="ds:label">
							<xsl:value-of select="ds:label" />
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="ds:imageId" />
						</xsl:otherwise>
					</xsl:choose>
				</td>
				<td>
					<xsl:choose>
						<xsl:when test="ds:descr">
							<xsl:value-of select="ds:descr" />
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="ds:imageLocation" />
						</xsl:otherwise>
					</xsl:choose>
				</td>
				<td><xsl:value-of select="ds:platform" /><xsl:text> </xsl:text><xsl:value-of select="ds:architecture" /></td>
				<td style="wrap: nowrap">
					<xsl:choose>
						<xsl:when test="ds:isOwner">
							<a onclick="appPopupAction('delete', null, '{ds:imageId}')" style="cursor:pointer"><img src="images/silk/cross.png" alt="Delete Image" title="Delete Image" /></a>
						</xsl:when>
						<xsl:when test="ds:isPublic">
							<a onclick="appPopupAction('detach', null, '{ds:imageId}')" style="cursor:pointer"><img src="images/silk/delete.png" alt="Remove image from this list" title="Remove image from this list" /></a>
						</xsl:when>
					</xsl:choose>
					<a onclick="appPopupAction('detail', null,'{ds:imageId}')" style="cursor:pointer"><img src="images/silk/magnifier.png" alt="Examine Image" title="Examine Image" /></a>
				</td>
			</tr>
		</xsl:if>
	</xsl:template>

	<xsl:template match="ds:image" mode="singleItem">
		<table>
			<tr><th>ID</th><td><xsl:value-of select="ds:imageId" /></td></tr>
			<tr><th>Name</th><td><xsl:value-of select="ds:name" /></td></tr>
			<tr><th>Description</th><td><xsl:value-of select="ds:descr" /></td></tr>
			<tr><th>Platform</th><td><xsl:value-of select="ds:platform" /><xsl:text> </xsl:text><xsl:value-of select="ds:architecture" /></td></tr>
			<tr>
				<th>Status</th>
				<td>
					<xsl:if test="ds:isPublic">public<xsl:text> </xsl:text></xsl:if>
					<xsl:if test="ds:isPaid">paid<xsl:text> </xsl:text></xsl:if>
					<xsl:if test="ds:isAmazon">amazon<xsl:text> </xsl:text></xsl:if>
					<xsl:if test="ds:isOwner">owner<xsl:text> </xsl:text></xsl:if>
				</td>
			</tr>
			<tr><th>Location</th><td><xsl:value-of select="ds:imageLocation" /></td></tr>
			<xsl:if test="ds:kernelId">
				<tr><th>Default&#160;Kernel</th><td><xsl:value-of select="ds:kernelId" /></td></tr>
			</xsl:if>
			<xsl:if test="ds:ramdiskId">
				<tr><th>Default&#160;Ramdisk</th><td><xsl:value-of select="ds:ramdiskId" /></td></tr>
			</xsl:if>
		</table>
	</xsl:template>

</xsl:stylesheet>