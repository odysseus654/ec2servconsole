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
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:ec2="http://ec2.amazonaws.com/doc/2009-04-04/" version="1.0">
	<xsl:param name="sort" />
	<xsl:param name="sortdir" />
	<xsl:param name="pageno" select="'1'" />
	<xsl:param name="rowsperpage" select="'100'" />
	
	<xsl:template match="ec2:requestId"></xsl:template>

	<xsl:template name="colSortHdr">
		<xsl:param name="col" />
		<xsl:param name="title" select="$col" />
		
		<th onclick="panelSort(this, '{$col}')" title="Sort by this field" style="cursor: default">
			<xsl:if test="$sort = $col">
				<xsl:choose>
					<xsl:when test="$sortdir='d'">
						<xsl:attribute name="class">sortdn</xsl:attribute>
					</xsl:when>
					<xsl:otherwise>
						<xsl:attribute name="class">sortup</xsl:attribute>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:if>
			<xsl:value-of select="$title" />
		</th>
	</xsl:template>
	
	<xsl:template match="ec2:DescribeImagesResponse">
		<xsl:choose>
			<xsl:when test="count(ec2:imagesSet/ec2:item) = 1">
				<xsl:apply-templates select="ec2:imagesSet/ec2:item" mode="singleItem" />
			</xsl:when>
			<xsl:otherwise>
				<xsl:apply-templates />
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
	<xsl:template match="ec2:imagesSet">
		<xsl:variable name="numpages" select="floor((((count(./ec2:item)) - 1) div $rowsperpage) + 1)" />
		<xsl:variable name="minpos" select="$rowsperpage * ($pageno - 1)" />
		<xsl:variable name="maxpos" select="$minpos + $rowsperpage - 1" />
		Page <xsl:value-of select="$pageno" /> of <xsl:value-of select="$numpages" />

		<table border="1">
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
				<xsl:call-template name="colSortHdr">
					<xsl:with-param name="col" select="'owner'" />
					<xsl:with-param name="title" select="'Owner Id'" />
				</xsl:call-template>
				<th></th>
			</tr>
			
			<xsl:choose>
				<xsl:when test="$sort = 'id'">
					<xsl:choose>
						<xsl:when test="$sortdir='d'">
							<xsl:apply-templates select="ec2:item[position() &gt;= $minpos and position() &lt;= $maxpos]" mode="imagesSet">
								<xsl:sort select="ec2:imageId" order="descending" />
							</xsl:apply-templates>
						</xsl:when>
						<xsl:otherwise>
							<xsl:apply-templates select="ec2:item[position() &gt;= $minpos and position() &lt;= $maxpos]" mode="imagesSet">
								<xsl:sort select="ec2:imageId" order="ascending" />
							</xsl:apply-templates>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:when test="$sort = 'loc'">
					<xsl:choose>
						<xsl:when test="$sortdir='d'">
							<xsl:apply-templates select="ec2:item[position() &gt;= $minpos and position() &lt;= $maxpos]" mode="imagesSet">
								<xsl:sort select="ec2:imageLocation" order="descending" />
							</xsl:apply-templates>
						</xsl:when>
						<xsl:otherwise>
							<xsl:apply-templates select="ec2:item[position() &gt;= $minpos and position() &lt;= $maxpos]" mode="imagesSet">
								<xsl:sort select="ec2:imageLocation" order="ascending" />
							</xsl:apply-templates>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:when test="$sort = 'arch'">
					<xsl:choose>
						<xsl:when test="$sortdir='d'">
							<xsl:apply-templates select="ec2:item[position() &gt;= $minpos and position() &lt;= $maxpos]" mode="imagesSet">
								<xsl:sort select="ec2:architecture" order="descending" />
							</xsl:apply-templates>
						</xsl:when>
						<xsl:otherwise>
							<xsl:apply-templates select="ec2:item[position() &gt;= $minpos and position() &lt;= $maxpos]" mode="imagesSet">
								<xsl:sort select="ec2:architecture" order="ascending" />
							</xsl:apply-templates>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:when test="$sort = 'owner'">
					<xsl:choose>
						<xsl:when test="$sortdir='d'">
							<xsl:apply-templates select="ec2:item[position() &gt;= $minpos and position() &lt;= $maxpos]" mode="imagesSet">
								<xsl:sort select="ec2:imageOwnerId" order="descending" />
							</xsl:apply-templates>
						</xsl:when>
						<xsl:otherwise>
							<xsl:apply-templates select="ec2:item[position() &gt;= $minpos and position() &lt;= $maxpos]" mode="imagesSet">
								<xsl:sort select="ec2:imageOwnerId" order="ascending" />
							</xsl:apply-templates>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:otherwise>
					<xsl:apply-templates select="ec2:item[position() &gt;= $minpos and position() &lt;= $maxpos]" mode="imagesSet" >
						<xsl:sort select="ec2:imageId" order="descending"/>
					</xsl:apply-templates>
				</xsl:otherwise>
			</xsl:choose>
		</table>
	</xsl:template>
	
	<xsl:template match="ec2:item" mode="imagesSet">
		<xsl:if test="ec2:imageType = 'machine'">
			<tr>
				<xsl:choose>
					<xsl:when test="ec2:imageState != 'available'">
						<xsl:attribute name="class">disabled</xsl:attribute>
					</xsl:when>
					<xsl:when test="ec2:productCodes">
						<xsl:attribute name="class">paid</xsl:attribute>
					</xsl:when>
					<xsl:when test="ec2:imageOwnerId = 'amazon'">
						<xsl:attribute name="class">amazon</xsl:attribute>
					</xsl:when>
					<xsl:when test="ec2:isPublic = 'true'">
						<xsl:attribute name="class">public</xsl:attribute>
					</xsl:when>
				</xsl:choose>
				<td style="wrap: nowrap">
					<xsl:choose>
						<xsl:when test="ec2:imageType = 'kernel'">
							<img src="images/silk/brick.png" alt="kernel" />
						</xsl:when>
						<xsl:when test="ec2:imageType = 'ramdisk'">
							<img src="images/silk/drive.png" alt="ramdisk" />
						</xsl:when>
						<xsl:when test="ec2:imageType = 'machine'">
							<img src="images/silk/computer.png" alt="machine" />
						</xsl:when>
					</xsl:choose>
					<xsl:value-of select="ec2:imageId" />
				</td>
				<td><xsl:value-of select="ec2:imageLocation" /></td>
				<td><xsl:value-of select="ec2:platform" /><xsl:text> </xsl:text><xsl:value-of select="ec2:architecture" /></td>
				<td><xsl:value-of select="ec2:imageOwnerId" /></td>
				<td style="wrap: nowrap">
					<a onclick="appPopupAction('add', null, '{ec2:imageId}')" style="cursor:pointer"><img src="images/silk/add.png" alt="Add" /></a>
					<a onclick="appPopupAction('detail', null,'{ec2:imageId}')" style="cursor:pointer"><img src="images/silk/magnifier.png" alt="Examine" /></a>
				</td>
			</tr>
		</xsl:if>
	</xsl:template>

	<xsl:template match="ec2:item" mode="singleItem">
		<table>
			<tr><th>ID</th><td><xsl:value-of select="ec2:imageId" /></td></tr>
			<tr><th>Type</th><td><xsl:value-of select="ec2:imageType" /></td></tr>
			<tr><th>Platform</th><td><xsl:value-of select="ec2:platform" /><xsl:text> </xsl:text><xsl:value-of select="ec2:architecture" /></td></tr>
			<tr>
				<th>Status</th>
				<td>
					<xsl:if test="ec2:isPublic = 'true'">public<xsl:text> </xsl:text></xsl:if>
					<xsl:if test="ec2:productCodes">paid<xsl:text> </xsl:text></xsl:if>
					<xsl:if test="ec2:imageState != 'available'">disabled<xsl:text> </xsl:text></xsl:if>
				</td>
			</tr>
			<tr><th>Location</th><td><xsl:value-of select="ec2:imageLocation" /></td></tr>
			<tr><th>Owner</th><td><xsl:value-of select="ec2:imageOwnerId" /></td></tr>
			<xsl:if test="ec2:productCodes">
				<tr><th>Product&#160;Code</th><td><xsl:apply-templates select="ec2:productCodes/ec2:item" mode="singleItemProduct" /></td></tr>
			</xsl:if>
			<xsl:if test="ec2:kernelId">
				<tr><th>Default&#160;Kernel</th><td><xsl:value-of select="ec2:kernelId" /></td></tr>
			</xsl:if>
			<xsl:if test="ec2:ramdiskId">
				<tr><th>Default&#160;Ramdisk</th><td><xsl:value-of select="ec2:ramdiskId" /></td></tr>
			</xsl:if>
		</table>
	</xsl:template>
	
	<xsl:template match="ec2:item" mode="singleItemProduct">
		<xsl:if test="position() != 1">
			<xsl:text>, </xsl:text>
		</xsl:if>
		<xsl:value-of select="ec2:productCode" />
	</xsl:template>
</xsl:stylesheet>