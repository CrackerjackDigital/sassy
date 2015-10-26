#!/bin/bash
echo "1/4: setting up environment"
if [ !-d vendor ]; then
	mkdir vendor
fi
echo "please don't build me please SilverStripe" > vendor/_manifest_exclude
echo "2/4: Removing current versions"
rm -vfr vendor/leafo components/scssphp
echo "3/4: Updating components"
composer update --prefer-dist --no-dev
echo "4/4: Removing cruft"
rm -vf components/*.js components/*.css
echo "done"
