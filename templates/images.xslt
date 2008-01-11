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
	xmlns:ec2="http://ec2.amazonaws.com/doc/2007-01-03/" version="1.0">
	<xsl:param name="sort" />
	<xsl:param name="sortdir" />

	<xsl:template name="colSortHdr">
		<xsl:param name="col" />
		<xsl:param name="title" select="$col" />
		
		<th onclick="panelSort(this, '{$col}')" title="Sort by this field">
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
		<xsl:apply-templates />
	</xsl:template>
	
	<xsl:template match="ec2:imagesSet">
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
					<xsl:with-param name="col" select="'owner'" />
					<xsl:with-param name="title" select="'Owner Id'" />
				</xsl:call-template>
				<th></th>
			</tr>
			
			<xsl:choose>
				<xsl:when test="$sort = 'id'">
					<xsl:choose>
						<xsl:when test="$sortdir='d'">
							<xsl:apply-templates select="ec2:item" mode="imagesSet">
								<xsl:sort select="ec2:imageId" order="descending" />
							</xsl:apply-templates>
						</xsl:when>
						<xsl:otherwise>
							<xsl:apply-templates select="ec2:item" mode="imagesSet">
								<xsl:sort select="ec2:imageId" order="ascending" />
							</xsl:apply-templates>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:when test="$sort = 'loc'">
					<xsl:choose>
						<xsl:when test="$sortdir='d'">
							<xsl:apply-templates select="ec2:item" mode="imagesSet">
								<xsl:sort select="ec2:imageLocation" order="descending" />
							</xsl:apply-templates>
						</xsl:when>
						<xsl:otherwise>
							<xsl:apply-templates select="ec2:item" mode="imagesSet">
								<xsl:sort select="ec2:imageLocation" order="ascending" />
							</xsl:apply-templates>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:when test="$sort = 'owner'">
					<xsl:choose>
						<xsl:when test="$sortdir='d'">
							<xsl:apply-templates select="ec2:item" mode="imagesSet">
								<xsl:sort select="ec2:imageOwnerId" order="descending" />
							</xsl:apply-templates>
						</xsl:when>
						<xsl:otherwise>
							<xsl:apply-templates select="ec2:item" mode="imagesSet">
								<xsl:sort select="ec2:imageOwnerId" order="ascending" />
							</xsl:apply-templates>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:otherwise>
					<xsl:apply-templates select="ec2:item" mode="imagesSet" >
						<xsl:sort select="ec2:imageId" order="descending"/>
					</xsl:apply-templates>
				</xsl:otherwise>
			</xsl:choose>
		</table>
	</xsl:template>
	
	<xsl:template match="ec2:item" mode="imagesSet">
		<tr>
			<xsl:choose>
				<xsl:when test="ec2:isPublic = 'true'">
					<xsl:attribute name="class">public</xsl:attribute>
				</xsl:when>
				<xsl:when test="ec2:imageOwnerId = 'amazon'">
					<xsl:attribute name="class">amazon</xsl:attribute>
				</xsl:when>
				<xsl:when test="ec2:productCodes">
					<xsl:attribute name="class">paid</xsl:attribute>
				</xsl:when>
				<xsl:when test="ec2:imageState != 'available'">
					<xsl:attribute name="class">disabled</xsl:attribute>
				</xsl:when>
			</xsl:choose>
			<td><xsl:value-of select="ec2:imageId" /></td>
			<td><xsl:value-of select="ec2:imageLocation" /></td>
			<td><xsl:value-of select="ec2:imageOwnerId" /></td>
			<td onclick="add(this,'{ec2:imageId}')">Add</td>
		</tr>
	</xsl:template>
</xsl:stylesheet>