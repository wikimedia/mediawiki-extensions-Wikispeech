## Install php-ast
Phan needs `php-ast` to run, unfortunately this does not come included in the MediaWiki-Vagrant installation. To install it follow these instructions (largely influenced by [Mainframe98](https://www.mediawiki.org/wiki/User:Mainframe98/Vagrant)). You can also try to run the associated shell script to automate this part.

From the vagrant home directory run `vagrant up` to spin up vagrant then run `vagrant ssh` to enter your vagrant shell.

### Inside Vagrant
Run the following and accept any prompts
```bash
wget http://pear.php.net/go-pear.phar
php go-pear.phar
sudo pear/bin/pecl install ast
```

Then create `/etc/php/7.2/cli/conf.d/15-ast.ini` with the following content
```
; configuration for php ast module
; priority=15
extension=ast.so
```

Exit the vagrant shell (`exit`)

### Up the memory of Vagrant
Create `Vagrantfile-extra.rb` with the following content
```ruby
Vagrant.configure('2') do |config|
      config.vm.provider :virtualbox do |vb|
          # See http://www.virtualbox.org/manual/ch08.html for additional options.
          vb.customize ['modifyvm', :id, '--memory', '3072']
      end
end
```

## To run phan
*Replace `Wikispeech` by the name of your MediaWiki extension as needed.*

Before running phan for the first time make sure you run `composer update` from the `/vagrant/mediawiki/extensions/Wikispeech` directory.

To run phan (from within the vagrant shell) execute the following command
```bash
/vagrant/mediawiki/extensions/Wikispeech/vendor/bin/phan -d /vagrant/mediawiki/extensions/Wikispeech/ -p
```
