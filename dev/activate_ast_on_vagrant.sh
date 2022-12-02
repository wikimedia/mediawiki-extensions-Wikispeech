#! /usr/bin/env bash
# Run from your vagrant directory after having called ´vagrant up`

vagrant ssh <<'ENDSSH'
echo -e '\e[46mdownload pear\e[0m'
wget http://pear.php.net/go-pear.phar

# install pear answering any prompts
echo -e '\e[46minstall pear\e[0m'
php go-pear.phar  <<'EOF'

n

EOF

echo -e '\e[46minstall ast\e[0m'
sudo pear/bin/pecl install ast

# add ast to php.ini
echo -e '\e[46madd ast to php.ini\e[0m'
sudo tee -a /etc/php/7.4/cli/conf.d/15-ast.ini > /dev/null << EOF
; configuration for php ast module
; priority=15
extension=ast.so
EOF

ENDSSH

echo -e '\e[46mcreate Vagrantfile-extra.rb\e[0m'
cat <<EOT >> Vagrantfile-extra.rb
Vagrant.configure('2') do |config|
      config.vm.provider :virtualbox do |vb|
          # See http://www.virtualbox.org/manual/ch08.html for additional options.
          vb.customize ['modifyvm', :id, '--memory', '3072']
      end
end
EOT
