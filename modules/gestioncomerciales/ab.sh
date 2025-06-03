rm -rf ../../var/cache/prod/*
rm -rf ../../var/cache/dev/*
git reset --hard HEAD 
git pull
chown  emolo.es_d1xqaxjcvh5:psacln * -R

