# Introduction
This repository can be used as a template for Continous Integration and Continuous Delivery of a laravel application.

The project utilizes gitlab-ci, docker and laravel envoy to accomplish this. If you are not familiar with these technologies it is recommended that you read up on them individually before attempting to use this project.

The build-notes.md file has a bunch of links that were useful to me while making of this repository, consult it if necessary. 

# Setup 

## Using the build directory
This directory contains all the necessary files to build, test and deploy your application. 
To get started:
1. Pull the directory into your existing repo using git submodule add <git_directory_url>
2. Edit php/.env to fit your application, __this will be the production .env file for you application.__ 

> NOTE: Don't move this file it will be moved to the proper location automatically when envoy deploys your application. Make sure to copy the application key from your development .env file to this file before you go to deploy your appplication. 

3. Move the php/Envoy.blade.php file and php/env.test files into the root of your php application directory. Edit these files to fit your application.
4. Move the .gitlab-ci.yml file into the root directory of your project. Edit this file to fit your application.
5. Set any environment variables necessary for your docker containers to build on the production server in the build/.env file
6. Edit code in docker-compose.gitlab.yml docker-compose.prod.yml to fit your application. 
  + The docker-compose.gitlab.yml is used to build the project on your CI server. 
  + The docker-compose.prod.yml file is used by envoy to deploy your application on your production server. 
> Note: If you are developing locally, edit the docker-compose.dev.yml file to fit your application. You can then spin up a local dev environment running the below commands from the root directory of your project. 
```
docker-compose -f build/docker-compose.dev.yml up -d 
```

## Setting up the staging or production server
1. Provision a server with the hosting service of your choice, here it's aws.
2. Once the server is setup ssh into it and run scripts/startup.sh as root (you can do this by copying its contents into a .sh file on the server and executing it)
```
sudo su
cd /opt
touch startup.sh
vi startup.sh
-- copy the script from scripts/startup.sh here then save and quit vim --
chmod +x startup.sh
./startup.sh
```
3. Open the id_rsa public key created by the startup script and use it to create a deploy key on gitlab or github, this will be used to authenticate the server when it runs git pull on your application repository https://docs.gitlab.com/ee/ssh/
```
 cat /home/deployer/.ssh/id_rsa.pub
 ```
3. Next,  Create a keypair that can be used by envoyer to ssh into your server, see https://aws.amazon.com/premiumsupport/knowledge-center/new-user-accounts-linux-instance/
4. Place the public key from that newly created keypair in /home/deployer/.ssh/authorized_keys (this is a file not a directory)
```
cp deployer_privkey /home/deployer/.ssh/authorized_keys
sudo chmod 600 -R /home/deployer/
```
5. You should now be able to ssh into your server as the deployer user
6. See build-notes.md for links to setup CI/CD and follow this tutorial -- https://docs.gitlab.com/ee/ci/examples/laravel_with_gitlab_and_envoy/
7. Use gitlab environment variables to set 
  + GITLAB_SECRET -> this would be the token used to access your gitlab docker registry
  + SSH_PRIVATE_KEY -> This would be the private key envoy user uses to ssh into your server (see step 3 and 4 above)