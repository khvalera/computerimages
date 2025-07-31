#!/bin/bash


find . -name "*.php" > files.list
xgettext --language=PHP --from-code=UTF-8 --keyword=__ --output=locales/messages.pot --files-from=files.list
msginit --locale=en_US.UTF-8 --no-translator --input=locales/messages.pot --output-file=locales/en_US.po
msgfmt locales/en_US.po -o locales/en_US.mo
rm files.list
# To create a new file with Ukrainian translation
#msginit --locale=uk_UA.UTF-8 --no-translator --input=locales/messages.pot --output-file=locales/uk_UA.po

# Ukrainian translation update
msgmerge --update locales/uk_UA.po locales/messages.pot

# Creation of mo
#msgfmt locales/uk_UA.po -o locales/uk_UA.mo

