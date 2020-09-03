#! /usr/bin/env bash
# Run from your vagrant directory after having called ´vagrant up`

vagrant ssh <<'ENDSSH'
echo 'download pear'
wget http://pear.php.net/go-pear.phar

# install pear answering any prompts
echo 'install pear'
php go-pear.phar  <<'EOF'

n

EOF

echo 'install ast'
sudo pear/bin/pecl install ast

# add ast to php.ini
echo 'add ast to php.ini'
sudo tee -a /etc/php/7.2/cli/conf.d/15-ast.ini > /dev/null << EOF
; configuration for php ast module
; priority=15
extension=ast.so
EOF

ENDSSH

echo 'create Vagrantfile-extra.rb'
cat <<EOT >> Vagrantfile-extra.rb
Vagrant.configure('2') do |config|
      config.vm.provider :virtualbox do |vb|
          # See http://www.virtualbox.org/manual/ch08.html for additional options.
          vb.customize ['modifyvm', :id, '--memory', '3072']
      end
end
EOT
