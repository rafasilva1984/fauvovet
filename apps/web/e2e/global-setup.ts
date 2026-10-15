import { startServers, stopServers } from './servers';

export default async function globalSetup() {
  const children = await startServers();
  return () => stopServers(children); // teardown
}
