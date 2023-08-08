# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# 
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

import logging
import sys
import os
import time
import traceback
import signal
from optparse import OptionParser
from os.path import join
import json
import argparse

import asyncio
import ssl
import websockets
import random

try:
	from jeedom.jeedom import *
except ImportError:
	print("Error: importing module jeedom.jeedom")
	sys.exit(1)

def read_socket():
	global JEEDOM_SOCKET_MESSAGE
	if not JEEDOM_SOCKET_MESSAGE.empty():
		logging.debug("Message received in socket JEEDOM_SOCKET_MESSAGE")
		message = json.loads(jeedom_utils.stripped(JEEDOM_SOCKET_MESSAGE.get()))
		if message['apikey'] != _apikey:
			logging.error("Invalid apikey from socket : " + str(message))
			return
		try:
			print ('read')
		except Exception as e:
			logging.error('Send command to demon error : '+str(e))

def listen():
	jeedom_socket.open()
	try:
		while 1:
			time.sleep(0.5)
			main()
	except KeyboardInterrupt:
		shutdown()

# ----------------------------------------------------------------------------

def handler(signum=None, frame=None):
	logging.debug("Signal %i caught, exiting..." % int(signum))
	shutdown()

def shutdown():
	logging.debug("Shutdown")
	logging.debug("Removing PID file " + str(_pidfile))
	try:
		os.remove(_pidfile)
	except:
		pass
	try:
		jeedom_socket.close()
	except:
		pass
	logging.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)

# ----------------------------------------------------------------------------

ssl_context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
ssl_context.check_hostname = False
ssl_context.verify_mode = ssl.CERT_NONE
        
def decode(b):
    e = {}
    d = list(b)
    c = d[0]
    f = c
    g = [c]
    h = 256
    o = h
    for b in range(1, len(d)):
        a = ord(d[b])
        a = d[b] if h > a else e.get(a, f + c)
        g.append(a)
        c = a[0]
        e[o] = f + c
        o+=1
        f = a

    return "".join(g)
    
async def run():    
    class userdata:
        is_connected = True

    while True:
        try:        
            logging.info("Sélection du serveur")
            hosts = ["ws1", "ws3", "ws7", "ws8"]
            uri = "wss://{}.blitzortung.org:443/".format(random.choice(hosts))            
            logging.info("url : " + str(uri))
            time.sleep(3)
            async with websockets.connect(uri, ssl=ssl_context) as websocket:
                logging.info("Connecté")                
                await websocket.send('{"a": 111}')
                while True:
                    msg = await websocket.recv()
                    data = json.loads(decode(msg))
                    sig = data.pop("sig", ())
                    data["sig_num"] = len(sig)
                    data.pop("alt")
                    data.pop("pol")
                    data.pop("mds")
                    data.pop("mcg")
                    data.pop("lonc")
                    data.pop("latc")
                    #logging.info("data : " + str(data))
                    #logging.info("latitude : " + str(args.latitude))
                    #logging.info("longitude : " + str(args.longitude))                    
                    #data["distance"] = getDistanceBetweenPoints(float(args.latitude), float(args.longitude), data["lat"], data["lon"], "kilometers")
                    jeedom_com.send_change_immediate(data)
        except websockets.ConnectionClosed:
            pass
        time.sleep(5)


def main():
    asyncio.get_event_loop().run_until_complete(run())

# ----------------------------------------------------------------------------

_log_level = "error"
_socket_port = 56023
_socket_host = 'localhost'
_pidfile = '/tmp/demond.pid'
_apikey = ''
_callback = ''
_cycle = 0.3

parser = argparse.ArgumentParser(
    description='Daemon for Blitzortung')
parser.add_argument("--device", help="Device", type=str)
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Callback", type=str)
parser.add_argument("--apikey", help="Apikey", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=str)
parser.add_argument("--pid", help="Pid file", type=str)
parser.add_argument("--socketport", help="Port for blitzortung server", type=str)
args = parser.parse_args()

if args.device:
	_device = args.device
if args.loglevel:
    _log_level = args.loglevel
if args.callback:
    _callback = args.callback
if args.apikey:
    _apikey = args.apikey
if args.pid:
    _pidfile = args.pid
if args.cycle:
    _cycle = float(args.cycle)
if args.socketport:
	_socket_port = args.socketport
		
_socket_port = int(_socket_port)

jeedom_utils.set_log_level(_log_level)

logging.info('Start demond')
logging.info('Log level : '+str(_log_level))
logging.info('Socket port : '+str(_socket_port))
logging.info('Socket host : '+str(_socket_host))
logging.info('PID file : '+str(_pidfile))
logging.info('Apikey : '+str(_apikey))

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)	

try:
    jeedom_utils.write_pid(str(_pidfile))
    jeedom_com = jeedom_com(apikey = _apikey,url = _callback,cycle=_cycle)
    if not jeedom_com.test():
        logging.error('Network communication issues. Please fixe your Jeedom network configuration.')
        shutdown()
    jeedom_socket = jeedom_socket(port=_socket_port,address=_socket_host)
    listen()
except Exception as e:
	logging.error('Fatal error : '+str(e))
	logging.info(traceback.format_exc())
	shutdown()