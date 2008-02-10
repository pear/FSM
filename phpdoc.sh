#!/bin/sh

phpdoc -f FSM.php -d FSM -t docs/api -p -ti "FSM Package API" -dn FSM -dc FSM -ed examples -i CVS/*
