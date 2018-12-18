#!/bin/sh

### Build containers and allocate ports on hostmachine
### ORPHAN is container with unlisted service in docker-compose file
/usr/local/bin/docker-compose up --build --force-recreate --remove-orphans -d

### store allocated port of webserver container
PORT_WEB=$(/usr/local/bin/docker-compose port webserver 8080)

# Configure nginx to redirect web access to the container
# Copy nginx configuration from a jenkins-wide template
# it contains definition of a proxy that redirects given URLs to the "webserver" container
# Note: The template is used by all jobs - changes in the template should be backward-compatible, or you can create a new template (effectively versioning it)
cp -f $WORKSPACE/.ci/docker/templates/nginx.conf /etc/nginx/conf.d/$JOB_NAME.conf

# Replace $SERVER_VIRTUAL_HOST $JOB_NAME and $PORT_WEB in the nginx configuration
sed -i "s/{{SERVER_VIRTUAL_HOST}}/$SERVER_VIRTUAL_HOST/" /etc/nginx/conf.d/$JOB_NAME.conf
sed -i "s/{{JOB_NAME}}/$JOB_NAME/" /etc/nginx/conf.d/$JOB_NAME.conf
sed -i "s/{{PORT_WEB}}/$PORT_WEB/" /etc/nginx/conf.d/$JOB_NAME.conf

# Reload nginx to apply the new configuration
# "jenkins" user has been allowed to run "nginx" command as super-user without password prompt via /etc/sudoers configuration
# see https://www.digitalocean.com/community/tutorials/how-to-edit-the-sudoers-file-on-ubuntu-and-centos#how-to-modify-the-sudoers-file
sudo nginx -s reload
