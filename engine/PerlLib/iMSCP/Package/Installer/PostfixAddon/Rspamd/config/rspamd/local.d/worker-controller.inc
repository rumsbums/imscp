# RSPAMD(8) Worker controller configuration file - auto-generated by i-MSCP
#     DO NOT EDIT THIS FILE BY HAND -- YOUR CHANGES WILL BE OVERWRITTEN

# See https://rspamd.com/doc/workers/controller.html

# You can add your own configuration in the
# '$LOCAL_CONFDIR/local.d/worker-controller.inc.local file

enabled = false;
count = 1;
password = "";

.include(try=true,priority=2,duplicate=merge) "${LOCAL_CONFDIR}/local.d/worker-controller.inc.local"