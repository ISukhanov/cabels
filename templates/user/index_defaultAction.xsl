<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template match="index_defaultAction">
		It's a text
		param equals <xsl:value-of select="test" />
	</xsl:template>

</xsl:stylesheet>