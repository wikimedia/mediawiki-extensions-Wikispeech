#! /usr/bin/env bash

filter=""

while getopts "f:" Option
do
  case $Option in
      f ) filter="--filter $OPTARG";;
  esac
done
shift $((OPTIND - 1))

path=$*
if [[ -z $path ]]
then
    path=/vagrant/mediawiki/extensions/Wikispeech/tests/phpunit
fi

PHPUNIT_WIKI=wiki php /vagrant/mediawiki/tests/phpunit/phpunit.php $filter $path
