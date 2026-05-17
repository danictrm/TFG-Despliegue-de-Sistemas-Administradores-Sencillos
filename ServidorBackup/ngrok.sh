curl -sSL https://ngrok-agent.s3.amazonaws.com/ngrok.asc \
  | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null \
  && echo "deb https://ngrok-agent.s3.amazonaws.com bookworm main" \
  | sudo tee /etc/apt/sources.list.d/ngrok.list \
  && sudo apt update \
  && sudo apt install ngrok

ngrok config add-authtoken 3BD1XpEJ4PxvpenKwKWsnFO1e4h_6tcQJbCB8XtsT6SbBVzMM



sudo ngrok service install --config /root/.config/ngrok/ngrok.yml


cat <<EOF >> /root/.config/ngrok/ngrok.yml

tunnels:
  panel:
    proto: http
    addr: 80
EOF


service apache2 restart
sudo systemctl enable ngrok
sudo systemctl start ngrok
sudo systemctl status ngrok

