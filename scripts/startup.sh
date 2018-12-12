#! /bin/bash

# Run this script as root 

# Install necessary system services
yum update -y && \
yum install -y git && \
amazon-linux-extras install docker -y && \
service docker start  && \
systemctl enable docker && \
usermod -aG docker ec2-user && \
curl -L https://github.com/docker/compose/releases/download/1.23.0-rc3/docker-compose-`uname -s`-`uname -m` -o /usr/local/bin/docker-compose && \
chmod +x /usr/local/bin/docker-compose

# If there is no www-data user create
# checking group existence https://stackoverflow.com/questions/29073210/how-to-check-if-a-group-exists-and-add-if-it-doesnt-in-linux-shell-script
if [ -z $( grep www-data /etc/group) ]
  then
    useradd -ru 1001 -U www-data  ## create a www-data system user -r and complimentary group -U of same name
fi

# Modify the www-data group to id 1001
# usermod -aG www-data ec2-user && \
groupmod -g 1001 www-data 

# Set permissions on the serve directory 
chown -R root:www-data /srv && \
chmod -R +2770 /srv

# If a deployer user doesn't exist create one 
if [ -z $( grep deployer /etc/passwd) ]
  then
    useradd deployer
    usermod -aG www-data deployer
    usermod -aG docker deployer

    # Then give that new user sudo access
    echo "deployer ALL=(ALL)NOPASSWD:ALL" >> /etc/sudoers
fi

# Next run some commands as the deployer user
# Add docker-compose path to path for deployer user
# Info on path and subshells and why export won't work https://askubuntu.com/questions/53177/bash-script-to-set-environment-variables-not-working
# and here https://stackoverflow.com/questions/18547881/shell-script-to-set-environment-variables
# and here https://unix.stackexchange.com/questions/21598/how-do-i-set-a-user-environment-variable-permanently-not-session
su deployer -c 'echo -e "PATH=/usr/local/bin:$PATH\n export PATH" >> ~/.bashrc'

# If no key file exists for the deployer user then create one
if [ ! -e /home/deployer/.ssh/id_rsa ]
  then 
    su deployer -c 'ssh-keygen -o -t rsa -b 4096 -f /home/deployer/.ssh/id_rsa -q -N ""'
    su deployer -c 'echo -e "Host *\n\tStrictHostKeyChecking no\n\n" > ~/.ssh/config'
    chmod 700 ~/.ssh
    chmod 600 ~/.ssh/id_rsa{,.pub}
    chmod 600 ~/.ssh/config
fi

# If no ssh key exists to log in as the deployer user create one
# if [! -e /home/deployer/.ssh/authorized_keys ]
#   then
#     ssh-keygen -o -t rsa -b 4096 -f /home/deployer/.ssh/authorized_keys -q -N ""
#     chmod 600 /home/deployer/.ssh/authorized_keys 
# fi 

# reboot