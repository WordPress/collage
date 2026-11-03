<?php

$zip = new ZipArchive();
$zip->open( dirname( __DIR__ ) . '/samples/a-good-place-to-start.docx', ZipArchive::CREATE | ZipArchive::OVERWRITE );
$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>' );
$zip->addFromString( '_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>' );
$zip->addFromString( 'word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
$zip->addFromString( 'word/styles.xml', '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:pPr><w:outlineLvl w:val="0"/></w:pPr></w:style><w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:pPr><w:outlineLvl w:val="1"/></w:pPr></w:style></w:styles>' );
$zip->addFromString( 'word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>A good place to start</w:t></w:r></w:p>
<w:p><w:r><w:t>Every good story starts somewhere. A thought on the train. A page in a notebook. A document that has been sitting on your desktop, waiting for its moment.</w:t></w:r></w:p>
<w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr><w:r><w:t>Make room for the good work</w:t></w:r></w:p>
<w:p><w:r><w:t xml:space="preserve">Bringing your words to WordPress should feel simple. Your </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>headings stay headings</w:t></w:r><w:r><w:t xml:space="preserve">. Your paragraphs get room to breathe. And everything becomes a block you can edit.</w:t></w:r></w:p>
<w:p><w:r><w:t>Here is a small plan for getting started:</w:t></w:r></w:p>
<w:tbl><w:tblPr/><w:tblGrid><w:gridCol w:w="3000"/><w:gridCol w:w="6000"/></w:tblGrid><w:tr><w:trPr><w:tblHeader/></w:trPr><w:tc><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>The step</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>The idea</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>Bring what you have</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>A rough draft is a perfectly good beginning.</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>Make it your own</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Rearrange, rewrite, and find your voice.</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>Share when you are ready</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>There is no rush. It is a draft until you say otherwise.</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
<w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr><w:r><w:t>The next chapter is yours</w:t></w:r></w:p>
<w:p><w:r><w:t>This is your starting point, not a finished product. Open the editor, move things around, and see where the words take you.</w:t></w:r></w:p>
<w:p><w:r><w:rPr><w:i/></w:rPr><w:t>Good ideas deserve a good home.</w:t></w:r></w:p>
<w:sectPr/></w:body></w:document>' );
$zip->close();
